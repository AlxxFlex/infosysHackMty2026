<?php

namespace Tests\Feature;

use App\Enums\EventType;
use App\Models\SimulationRun;
use App\Services\ShiftService;
use App\Services\SimulationEventService;
use App\Services\SimulatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulationEventServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");
        if ($connection !== 'mysql' || $database !== 'infoSys_testing') {
            throw new \LogicException('Event tests may only refresh the dedicated MySQL database infoSys_testing.');
        }
    }

    public function test_scheduled_events_apply_once_in_stable_order_and_reoptimize_both_agents(): void
    {
        $run = app(ShiftService::class)->startRun(app(ShiftService::class)->createRun('demo_normal', 88));
        $run = app(SimulatorService::class)->tick($run, 40, 'events-40');
        $run = $run->fresh();
        $this->assertSame('1.4000', (string) $run->traffic_factor);
        $traffic = $run->events()->where('type', EventType::TRAFFIC_CHANGED->value)->firstOrFail();
        $this->assertNotNull($traffic->applied_at);
        $this->assertNotEmpty($traffic->state_after['reoptimization']);
        $this->assertSame(1, $run->events()->whereNotNull('applied_at')->count());

        app(SimulatorService::class)->tick($run, 1, 'events-40-repeat');
        $this->assertSame(1, SimulationRun::findOrFail($run->id)->events()->whereNotNull('applied_at')->count());

        $run = app(SimulatorService::class)->tick($run, 20, 'events-60');
        $run = $run->fresh();
        $this->assertSame(2, $run->events()->whereNotNull('applied_at')->count());
        $this->assertNotEmpty($run->environment_state['surge_zones']);
        $this->assertSame('TEC', $run->environment_state['surge_zones'][0]['zone']);
        $this->assertTrue($run->orders()->where('destination_zone', 'TEC')->where('surge_bonus_mxn', '>', 0)->exists());
    }

    public function test_dynamic_event_injection_is_validated_and_a_closure_increments_route_version(): void
    {
        $run = app(ShiftService::class)->createRun('demo_normal', 90);
        $event = app(SimulationEventService::class)->schedule($run, EventType::ROAD_CLOSED, $run->simulated_started_at->copy()->addMinutes(1), ['closure_id' => 'C-1', 'is_simulated' => true], 2);
        $this->assertSame(EventType::ROAD_CLOSED, $event->type);
        app(SimulationEventService::class)->applyDue($run, $run->simulated_started_at->copy()->addMinutes(1));
        $updated = $run->fresh();
        $this->assertSame(1, $updated->closure_version);
        $this->assertSame('C-1', $updated->environment_state['road_closures'][0]['closure_id']);
    }
}
