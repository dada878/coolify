<?php

namespace App\Livewire\Concerns;

use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Renderless;

trait ListsProjects
{
    #[Computed]
    public function projects(): Collection
    {
        return Project::ownedByCurrentTeam()
            ->with(['environments:id,uuid,name,project_id'])
            ->withCount([
                'applications',
                'services',
                'postgresqls',
                'redis',
                'keydbs',
                'dragonflies',
                'clickhouses',
                'mongodbs',
                'mysqls',
                'mariadbs',
                'sqlites',
            ])
            ->get();
    }

    #[Renderless]
    public function setProjectPinned(string $uuid, bool $pinned): void
    {
        $project = Project::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('view', $project);
        $key = ['user_id' => auth()->id(), 'project_id' => $project->id];
        $pins = DB::table('project_user_pins');

        if ($pinned) {
            $pins->insertOrIgnore($key);
        } else {
            $pins->where($key)->delete();
        }
    }

    protected function projectRows(): array
    {
        $pins = DB::table('project_user_pins')->where('user_id', auth()->id())
            ->whereIn('project_id', $this->projects->modelKeys())->pluck('project_id')->flip();

        return $this->projects->map(function (Project $project) use ($pins): array {
            $firstEnvironment = $project->environments->first();
            $resourceCount = collect([
                $project->applications_count,
                $project->services_count,
                $project->postgresqls_count,
                $project->redis_count,
                $project->keydbs_count,
                $project->dragonflies_count,
                $project->clickhouses_count,
                $project->mongodbs_count,
                $project->mysqls_count,
                $project->mariadbs_count,
                $project->sqlites_count,
            ])->sum();

            return [
                'uuid' => $project->uuid,
                'name' => $project->name,
                'pinned' => $pins->has($project->id),
                'description' => $project->description,
                'iconUrl' => $project->icon_path ? project_icon_url($project) : null,
                'href' => $project->navigateTo(),
                'environmentCount' => $project->environments->count(),
                'resourceCount' => $resourceCount,
                'createdAt' => $project->created_at?->format('M j, Y') ?? '-',
                'settingsHref' => auth()->user()->can('update', $project)
                    ? route('project.edit', ['project_uuid' => $project->uuid])
                    : null,
                'addResourceHref' => $firstEnvironment && auth()->user()->can('createAnyResource', [$firstEnvironment])
                    ? route('project.resource.create', [
                        'project_uuid' => $project->uuid,
                        'environment_uuid' => $firstEnvironment->uuid,
                    ])
                    : null,
            ];
        })->values()->toArray();
    }
}
