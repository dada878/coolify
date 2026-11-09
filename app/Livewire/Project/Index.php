<?php

namespace App\Livewire\Project;

use App\Livewire\Concerns\RemembersCanvasView;
use App\Models\Project;
use App\Services\ProjectRuntimeStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Renderless;
use Livewire\Component;

class Index extends Component
{
    use RemembersCanvasView;

    private const SORT_LABELS = [
        'running-desc' => 'Most running resources',
        'name-asc' => 'Name A–Z',
        'name-desc' => 'Name Z–A',
        'resources-desc' => 'Most resources',
        'environments-desc' => 'Most environments',
        'running-asc' => 'Fewest running resources',
        'resources-asc' => 'Fewest resources',
        'created-asc' => 'Oldest first',
        'created-desc' => 'Newest first',
        'description-asc' => 'Description A–Z',
        'description-desc' => 'Description Z–A',
    ];

    public $projects;

    public function mount(): void
    {
        if ($this->prefersCanvasView('projects')) {
            $this->redirectRoute('project.canvas.index', navigate: true);

            return;
        }

        // Only load what the page renders. Servers/private keys were previously
        // hydrated into public Livewire state but never used by the view.
        $this->projects = Project::ownedByCurrentTeam()
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
    public function refreshRuntimeStatuses(ProjectRuntimeStatus $runtime): void
    {
        $this->dispatch('project-status-refreshed', statuses: $runtime->forCurrentTeam());
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

    #[Renderless]
    public function saveProjectSort(string $sort): void
    {
        validator(['sort' => $sort], ['sort' => ['required', Rule::in(array_keys(self::SORT_LABELS))]])->validate();
        DB::table('project_list_sort_preferences')->upsert(
            [['user_id' => auth()->id(), 'sort' => $sort]], ['user_id'], ['sort']
        );
    }

    public function render(): View
    {
        $pins = DB::table('project_user_pins')->where('user_id', auth()->id())
            ->whereIn('project_id', $this->projects->modelKeys())->pluck('project_id')->flip();

        $sort = DB::table('project_list_sort_preferences')->where('user_id', auth()->id())->value('sort');

        return view('livewire.project.index', [
            'savedSort' => isset(self::SORT_LABELS[$sort]) ? $sort : 'name-asc',
            'sortLabels' => self::SORT_LABELS,
            'runtimeStatuses' => app(ProjectRuntimeStatus::class)->forCurrentTeam(),
            'projectsJs' => $this->projects->map(function (Project $project) use ($pins): array {
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
            })->values()->toArray(),
        ]);
    }
}
