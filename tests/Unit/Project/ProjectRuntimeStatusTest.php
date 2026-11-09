<?php

use App\Services\ProjectRuntimeStatus;

it('shows how many resources run alongside their aggregate condition', function (array $statuses, string $state, int $running) {
    expect(ProjectRuntimeStatus::summarize(collect($statuses)))->toBe([
        'state' => $state,
        'running' => $running,
        'total' => count($statuses),
    ]);
})->with([
    'empty project' => [[], 'Empty', 0],
    'all running' => [['running:unknown', 'running:healthy'], 'Running', 2],
    'mixed project' => [['exited:unhealthy', 'running:healthy'], 'Partial', 1],
    'paused and stopped' => [['paused:unknown', 'exited'], 'Stopped', 0],
    'starting service' => [['starting', 'running:healthy'], 'Starting', 1],
    'unhealthy running resource' => [['running:unhealthy:excluded'], 'Degraded', 1],
    'partial compose stack' => [['degraded:unhealthy'], 'Degraded', 0],
    'unreported resource' => [[null, 'exited'], 'Unknown', 0],
]);
