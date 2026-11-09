<?php

namespace App\Livewire\Project;

use App\Livewire\Concerns\ListsProjects;
use App\Livewire\Concerns\RemembersCanvasView;
use App\Services\ProjectRuntimeStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Renderless;
use Livewire\Component;

class Index extends Component
{
    use ListsProjects, RemembersCanvasView;

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

    public function mount(): void
    {
        if ($this->prefersCanvasView('projects')) {
            $this->redirectRoute('project.canvas.index', navigate: true);

            return;
        }
    }

    #[Renderless]
    public function refreshRuntimeStatuses(ProjectRuntimeStatus $runtime): void
    {
        $this->dispatch('project-status-refreshed', statuses: $runtime->forCurrentTeam());
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
        $sort = DB::table('project_list_sort_preferences')->where('user_id', auth()->id())->value('sort');

        return view('livewire.project.index', [
            'savedSort' => isset(self::SORT_LABELS[$sort]) ? $sort : 'name-asc',
            'sortLabels' => self::SORT_LABELS,
            'runtimeStatuses' => app(ProjectRuntimeStatus::class)->forCurrentTeam(),
            'projects' => $this->projects,
            'projectsJs' => $this->projectRows(),
        ]);
    }
}
