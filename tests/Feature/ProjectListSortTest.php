<?php

use App\Livewire\Project\Index;
use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::unguarded(fn () => InstanceSettings::query()->create(['id' => 0]));
    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();
    $this->user->teams()->attach($this->team, ['role' => 'owner']);
    $this->actingAs($this->user);
    session(['currentTeam' => $this->team]);
});

it('restores the last chosen column and direction for the signed-in user only', function () {
    Livewire::test(Index::class)->assertViewHas('savedSort', 'name-asc')
        ->call('saveProjectSort', 'running-desc')
        ->call('saveProjectSort', 'running-asc');
    Livewire::test(Index::class)->assertViewHas('savedSort', 'running-asc');

    $other = User::factory()->create();
    $other->teams()->attach($this->team, ['role' => 'owner']);
    $this->actingAs($other);
    Livewire::test(Index::class)->assertViewHas('savedSort', 'name-asc');
});

it('retains the previous preference when given an unsupported sort', function () {
    Livewire::test(Index::class)->call('saveProjectSort', 'environments-desc')
        ->call('saveProjectSort', 'invalid-desc')->assertHasErrors('sort');
    Livewire::test(Index::class)->assertViewHas('savedSort', 'environments-desc');
});
