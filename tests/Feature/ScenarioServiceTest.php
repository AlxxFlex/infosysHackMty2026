<?php

namespace Tests\Feature;

use App\Enums\AgentType;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidScenarioDefinition;
use App\Models\DemandZone;
use App\Models\SimulationRun;
use App\Services\ScenarioService;
use App\Services\ShiftService;
use App\Services\SimulatorService;
use Database\Seeders\DemandZoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScenarioServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");
        if ($connection !== 'mysql' || $database !== 'infoSys_testing') {
            throw new \LogicException('Scenario tests may only refresh the dedicated MySQL database infoSys_testing.');
        }
    }

    public function test_demo_is_versioned_simulated_and_has_at_least_five_offers(): void
    {
        $scenario = app(ScenarioService::class)->load('demo_normal', 42);

        $this->assertGreaterThanOrEqual(5, count($scenario['orders']));
        $this->assertTrue($scenario['metadata']['is_simulated']);
        $this->assertSame('simulated', $scenario['metadata']['payments']);
        $this->assertSame('simulated', $scenario['metadata']['demand']);
        $this->assertNotEmpty($scenario['events']);
    }

    public function test_same_seed_materializes_equivalent_shared_offers(): void
    {
        $service = app(ShiftService::class);
        $first = $service->createRun('demo_normal', 42);
        $second = $service->createRun('demo_normal', 42);

        $projection = static fn (SimulationRun $run): array => $run->orders()->orderBy('scenario_order_key')->get()->map(fn ($order): array => [
            $order->scenario_order_key,
            $order->external_id,
            $order->spawn_time->toIso8601String(),
            $order->expires_at->toIso8601String(),
            (string) $order->base_pay_mxn,
        ])->all();

        $this->assertSame($projection($first), $projection($second));
        $this->assertSame($first->orders()->count() * 2, $first->shifts()->withCount('shiftOrders')->get()->sum('shift_orders_count'));
        $this->assertSame(2, $first->events()->whereNull('applied_at')->count());
    }

    public function test_challenge_generator_is_local_and_seedable(): void
    {
        $service = app(ScenarioService::class);
        $sameA = $service->load('challenge', 101);
        $sameB = $service->load('challenge', 101);
        $different = $service->load('challenge', 102);

        $this->assertSame($sameA['orders'], $sameB['orders']);
        $this->assertNotSame($sameA['orders'], $different['orders']);
    }

    public function test_spawn_and_expiry_are_independent_but_timestamp_identical_for_both_agents(): void
    {
        $shiftService = app(ShiftService::class);
        $simulator = app(SimulatorService::class);
        $run = $shiftService->startRun($shiftService->createRun('demo_normal', 9));

        $before = $run->orders()->where('scenario_order_key', 'demo-003')->firstOrFail();
        $this->assertSame(OrderStatus::PENDING, $before->shiftOrders()->firstOrFail()->status);

        $atSpawn = $simulator->tick($run, 1, 'scenario-spawn');
        $spawnStates = $atSpawn->shifts()->with(['shiftOrders.order'])->get()->mapWithKeys(fn ($shift) => [
            $shift->agent_type->value => $shift->shiftOrders->firstWhere('order.scenario_order_key', 'demo-001'),
        ]);
        $this->assertSame(OrderStatus::AVAILABLE, $spawnStates[AgentType::COURIER_AI->value]->status);
        $this->assertSame(OrderStatus::AVAILABLE, $spawnStates[AgentType::BASELINE->value]->status);
        $this->assertSame(
            $spawnStates[AgentType::COURIER_AI->value]->available_at->getTimestamp(),
            $spawnStates[AgentType::BASELINE->value]->available_at->getTimestamp(),
        );

        $aiOrder = $spawnStates[AgentType::COURIER_AI->value];
        $aiOrder->forceFill(['status' => OrderStatus::ACCEPTED, 'accepted_at' => $atSpawn->simulated_current_at])->save();
        $expired = $simulator->tick($atSpawn, 25, 'scenario-expiry');
        $states = $expired->shifts()->with(['shiftOrders.order'])->get()->mapWithKeys(fn ($shift) => [
            $shift->agent_type->value => $shift->shiftOrders->firstWhere('order.scenario_order_key', 'demo-001'),
        ]);

        $this->assertSame(OrderStatus::ACCEPTED, $states[AgentType::COURIER_AI->value]->status);
        $this->assertSame(OrderStatus::EXPIRED, $states[AgentType::BASELINE->value]->status);
    }

    public function test_invalid_scenario_fails_before_creating_a_run(): void
    {
        $this->expectException(InvalidScenarioDefinition::class);
        try {
            app(ShiftService::class)->createRun('missing_scenario', 1);
        } finally {
            $this->assertSame(0, SimulationRun::query()->count());
        }
    }

    public function test_demand_zone_seeder_is_idempotent_for_demo_zones(): void
    {
        $this->seed(DemandZoneSeeder::class);
        $this->seed(DemandZoneSeeder::class);

        $this->assertSame(3, DemandZone::query()->whereIn('zone_key', ['CENTRO', 'TEC', 'SAN_PEDRO'])->count());
    }
}
