<?php

use App\Livewire\Project\Index;
use App\Models\InstanceSettings;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    InstanceSettings::unguarded(fn () => InstanceSettings::query()->create(['id' => 0]));
    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();
    $this->user->teams()->attach($this->team, ['role' => 'owner']);
    $this->actingAs($this->user);
    session(['currentTeam' => $this->team]);
    $this->project = Project::factory()->create(['team_id' => $this->team->id]);
});

it('remembers a project pin for its owner and accepts repeated requests', function () {
    Livewire::test(Index::class)
        ->call('setProjectPinned', $this->project->uuid, true)
        ->call('setProjectPinned', $this->project->uuid, true);
    $this->assertDatabaseCount('project_user_pins', 1);
    Livewire::test(Index::class)->assertViewHas('projectsJs', fn ($projects) => $projects[0]['pinned'] === true);

    $other = User::factory()->create();
    $other->teams()->attach($this->team, ['role' => 'owner']);
    $this->actingAs($other);
    Livewire::test(Index::class)->assertViewHas('projectsJs', fn ($projects) => $projects[0]['pinned'] === false)
        ->call('setProjectPinned', $this->project->uuid, false);
    $this->assertDatabaseCount('project_user_pins', 1);

    $this->actingAs($this->user);
    Livewire::test(Index::class)->call('setProjectPinned', $this->project->uuid, false);
    $this->assertDatabaseEmpty('project_user_pins');
});

it('cannot pin a project outside the current team', function () {
    $another = Project::factory()->create(['team_id' => Team::factory()->create()->id]);
    expect(fn () => Livewire::test(Index::class)->call('setProjectPinned', $another->uuid, true))
        ->toThrow(ModelNotFoundException::class);
    $this->assertDatabaseEmpty('project_user_pins');
});
