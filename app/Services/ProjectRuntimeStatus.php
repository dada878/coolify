<?php

namespace App\Services;

use App\Models\Environment;
use App\Models\Project;
use Illuminate\Support\Collection;

class ProjectRuntimeStatus
{
    /**
     * Count each resource once across all accessible environments. A Compose
     * service contributes its own aggregate status, just like the resource list.
     */
    public function forCurrentTeam(?array $projectUuids = null): array
    {
        $projects = Project::ownedByCurrentTeam()
            ->when($projectUuids !== null, fn ($query) => $query->whereIn('uuid', $projectUuids))
            ->pluck('uuid', 'id');
        if ($projects->isEmpty()) {
            return [];
        }

        $relations = [
            'applications:id,environment_id,status',
            'applications.additional_servers:id',
            'services:id,uuid,environment_id',
            'services.applications:id,service_id,status,exclude_from_status',
            'services.databases:id,service_id,status,exclude_from_status',
        ];
        foreach (EnvironmentCanvas::DATABASE_RELATIONS as $database) {
            $relations[] = $database.':id,environment_id,status';
        }
        $environments = Environment::whereIn('project_id', $projects->keys())
            ->with($relations)->get(['id', 'project_id']);
        $starting = array_flip(app(EnvironmentCanvas::class)->startingServiceUuids($environments->pluck('services')->flatten(1)));
        $byProject = $environments->groupBy('project_id');

        return $projects->mapWithKeys(function (string $uuid, int $id) use ($byProject, $starting): array {
            $statuses = collect();
            foreach ($byProject->get($id, []) as $environment) {
                foreach ($environment->services as $service) {
                    $statuses->push(isset($starting[$service->uuid]) ? 'starting' : $service->containerStatus());
                }
                foreach (['applications', ...EnvironmentCanvas::DATABASE_RELATIONS] as $relation) {
                    $statuses = $statuses->concat($environment->{$relation}->pluck('status'));
                }
            }

            return [$uuid => self::summarize($statuses)];
        })->all();
    }

    public static function summarize(Collection $statuses): array
    {
        $total = $statuses->count();
        $states = $statuses->map(fn (?string $status) => str($status)->before(':')->value());
        $running = $states->filter(fn ($state) => $state === 'running')->count();
        $state = match (true) {
            $total === 0 => 'Empty',
            $states->intersect(['degraded', 'dead', 'removing'])->isNotEmpty(),
            $statuses->contains(fn ($status) => str($status)->startsWith('running:unhealthy')) => 'Degraded',
            $states->intersect(['starting', 'created', 'restarting'])->isNotEmpty() => 'Starting',
            $running === $total => 'Running',
            $running > 0 => 'Partial',
            $states->every(fn ($state) => in_array($state, ['exited', 'stopped', 'paused'], true)) => 'Stopped',
            default => 'Unknown',
        };

        return ['state' => $state, 'running' => $running, 'total' => $total];
    }
}
