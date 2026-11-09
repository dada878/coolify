<?php

use App\Livewire\Dashboard;
use App\Livewire\Project\Index;
use App\Models\Application;
use App\Models\InstanceSettings;
use App\Models\PrivateKey;
use App\Models\Project;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();

    InstanceSettings::unguarded(fn () => InstanceSettings::query()->create(['id' => 0]));

    $this->user = User::factory()->create();
    $this->team = Team::factory()->create();
    $this->user->teams()->attach($this->team, ['role' => 'owner']);

    $this->actingAs($this->user);
    session(['currentTeam' => $this->team]);
});

it('supplies every accessible project for sorting before the browser limits the dashboard list', function () {
    foreach (range(1, Dashboard::ITEM_LIMIT + 1) as $number) {
        Project::factory()->create([
            'team_id' => $this->team->id,
            'name' => sprintf('sort-project-%02d', $number),
            'description' => 'description '.(10 - $number),
        ]);
    }

    Livewire::test(Dashboard::class)
        ->assertViewHas('projectsJs', fn ($rows) => count($rows) === Dashboard::ITEM_LIMIT + 1)
        ->call('setProjectSort', 'name-desc')
        ->assertSet('projectSort', 'name-desc')
        ->call('setProjectSort', 'description-asc')
        ->call('setProjectSort', 'environments-desc')
        ->assertSet('projectSort', 'description-asc');
});

it('sorts servers by name and status', function () {
    $privateKey = PrivateKey::factory()->create(['team_id' => $this->team->id]);
    $servers = collect(['alpha-host', 'bravo-host', 'charlie-host'])->map(fn (string $name) => Server::factory()->create([
        'team_id' => $this->team->id,
        'private_key_id' => $privateKey->id,
        'name' => $name,
    ]));
    $servers[1]->settings->update(['is_reachable' => true, 'is_usable' => true]);
    $servers[2]->settings->update(['force_disabled' => true]);

    Livewire::test(Dashboard::class)
        ->assertSeeInOrder(['alpha-host', 'bravo-host', 'charlie-host'])
        ->call('setServerSort', 'name-desc')
        ->assertSeeInOrder(['charlie-host', 'bravo-host', 'alpha-host'])
        ->call('setServerSort', 'status-asc')
        ->assertSeeHtml('aria-label="Sort by status, sorted ascending"')
        // Disabled, Ready, Unavailable
        ->assertSeeInOrder(['charlie-host', 'bravo-host', 'alpha-host'])
        ->call('setServerSort', 'status')
        ->assertSet('serverSort', 'status-asc');
});

it('refreshes runtime counts across every accessible project so a sort can change the visible eight', function () {
    $projects = collect(range(1, Dashboard::ITEM_LIMIT + 1))->map(fn (int $n) => Project::factory()->create([
        'team_id' => $this->team->id,
        'name' => sprintf('project-%02d', $n),
    ]));
    $first = $projects->first();
    $app = Application::factory()->create(['environment_id' => $first->environments()->firstOrFail()->id]);
    $app->update(['status' => 'running:healthy']);

    $dashboard = Livewire::test(Dashboard::class)
        ->assertSet('projectRuntimeStatuses.'.$first->uuid, ['state' => 'Running', 'running' => 1, 'total' => 1]);
    expect(array_keys($dashboard->get('projectRuntimeStatuses')))->toHaveCount(Dashboard::ITEM_LIMIT + 1)
        ->toContain($projects->last()->uuid);

    $app->update(['status' => 'exited:unhealthy']);
    $dashboard->call('refreshProjectStatuses')
        ->assertSet('projectRuntimeStatuses.'.$first->uuid, ['state' => 'Stopped', 'running' => 0, 'total' => 1])
        ->call('setProjectSort', 'name-desc')
        ->assertSet('projectRuntimeStatuses.'.$projects->last()->uuid, ['state' => 'Empty', 'running' => 0, 'total' => 0]);
});

it('restores the dashboard sort and uses the same personal pins as Projects', function () {
    $project = Project::factory()->create(['team_id' => $this->team->id]);
    Livewire::test(Dashboard::class)->call('setProjectSort', 'running-desc')
        ->assertSet('projectSort', 'running-desc')
        ->call('setProjectPinned', $project->uuid, true);

    $cookie = 'coolify_'.$this->user->id.'_dashboard_projects_sort';
    expect(Cookie::queued($cookie)?->getValue())->toBe('running-desc');
    $this->withCookie($cookie, 'running-desc')->get(route('dashboard'))->assertOk()
        ->assertSee('projectListControls(', false);
    Livewire::withCookies([$cookie => 'running-desc'])->test(Dashboard::class)
        ->assertSet('projectSort', 'running-desc');
    Livewire::test(Index::class)
        ->assertViewHas('projectsJs', fn ($rows) => $rows[0]['pinned'] === true)
        ->call('setProjectPinned', $project->uuid, false);
    Livewire::test(Dashboard::class)->assertViewHas('projectsJs', fn ($rows) => $rows[0]['pinned'] === false);
});
