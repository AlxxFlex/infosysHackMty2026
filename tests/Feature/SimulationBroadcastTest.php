<?php

namespace Tests\Feature;

use App\Events\SimulationUpdated;
use App\Services\ShiftService;
use App\Services\SimulatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SimulationBroadcastTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");
        if ($connection !== 'mysql' || $database !== 'infoSys_testing') {
            throw new \LogicException('Broadcast tests may only refresh the dedicated MySQL database infoSys_testing.');
        }
    }

    public function test_committed_changes_dispatch_small_versioned_payloads_on_run_channel(): void
    {
        Event::fake([SimulationUpdated::class]);
        $shiftService = app(ShiftService::class);
        $run = $shiftService->startRun($shiftService->createRun('demo_normal', 123));
        app(SimulatorService::class)->tick($run, 1, 'broadcast-1');

        Event::assertDispatched(SimulationUpdated::class, function (SimulationUpdated $event) use ($run): bool {
            $payload = $event->broadcastWith();

            return $event->runId === $run->id && $event->type === 'order.available' && $payload['version'] === 'v1' && $payload['data']['is_simulated'] === true;
        });
    }

    public function test_dynamic_event_dispatches_environment_and_ranking_signals(): void
    {
        Event::fake([SimulationUpdated::class]);
        $shiftService = app(ShiftService::class);
        $run = $shiftService->startRun($shiftService->createRun('demo_normal', 124));
        app(SimulatorService::class)->tick($run, 40, 'broadcast-traffic');

        Event::assertDispatched(SimulationUpdated::class, fn (SimulationUpdated $event): bool => $event->type === 'traffic.changed');
        Event::assertDispatched(SimulationUpdated::class, fn (SimulationUpdated $event): bool => $event->type === 'ranking.updated');
    }
}
