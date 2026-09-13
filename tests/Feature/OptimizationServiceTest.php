<?php

namespace Tests\Feature;

use App\Enums\AgentType;
use App\Enums\OrderStatus;
use App\Models\Recommendation;
use App\Models\Shift;
use App\Models\SimulationRun;
use App\Services\OptimizationService;
use App\Services\RecommendationService;
use App\Services\ShiftService;
use App\Services\SimulatorService;
use App\Support\StateMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OptimizationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");
        if ($connection !== 'mysql' || $database !== 'infoSys_testing') {
            throw new \LogicException('Optimization tests may only refresh the dedicated MySQL database infoSys_testing.');
        }
    }

    public function test_demo_selects_a_feasible_two_order_plan_with_valid_pickup_dropoff_sequence(): void
    {
        config()->set('courier.routing.provider', 'fallback');
        Cache::flush();
        [$run, $shift] = $this->availableDemo();
        $environment = app(StateMapper::class)->toEnvironmentState($run);
        $courier = app(StateMapper::class)->toCourierState($shift, $run);
        $orders = $shift->shiftOrders()->with('order')->available()->get()->pluck('order');

        $plan = app(OptimizationService::class)->optimize($courier, $environment, $orders);

        $this->assertTrue($plan->feasible);
        $this->assertCount(2, $plan->orderIds);
        $this->assertCount(4, $plan->sequence);
        $positions = [];
        foreach ($plan->sequence as $index => $stop) {
            $this->assertContains($stop['action'], ['PICKUP', 'DROPOFF']);
            if ($stop['action'] === 'PICKUP') {
                $positions['p-'.$stop['order_id']] = $index;
            } else {
                $positions['d-'.$stop['order_id']] = $index;
            }
        }
        foreach ($plan->orderIds as $id) {
            $this->assertLessThan($positions['d-'.$id], $positions['p-'.$id]);
        }
        $this->assertGreaterThanOrEqual(0, $plan->batchSavingsDistanceKm);
        $this->assertGreaterThanOrEqual(0, $plan->detourRatio);
        $this->assertGreaterThanOrEqual(0, $plan->routeOverlap);
        $this->assertLessThanOrEqual(1, $plan->routeOverlap);
    }

    public function test_capacity_and_deadline_pruning_falls_back_to_a_single_or_empty_plan(): void
    {
        config()->set('courier.routing.provider', 'fallback');
        Cache::flush();
        [$run, $shift] = $this->availableDemo();
        $shift->forceFill(['max_concurrent_orders' => 1])->save();
        $shift = $shift->fresh();
        $environment = app(StateMapper::class)->toEnvironmentState($run);
        $courier = app(StateMapper::class)->toCourierState($shift, $run);
        $orders = $shift->shiftOrders()->with('order')->available()->get()->pluck('order');

        $plan = app(OptimizationService::class)->optimize($courier, $environment, $orders);

        $this->assertTrue($plan->feasible);
        $this->assertCount(1, $plan->orderIds);
        $this->assertLessThanOrEqual(1, count($plan->orderIds));
    }

    public function test_same_state_has_deterministic_winner_and_recommendation_is_persisted_pending(): void
    {
        config()->set('courier.routing.provider', 'fallback');
        Cache::flush();
        [$run, $shift] = $this->availableDemo();
        $first = app(RecommendationService::class)->recommend($shift, $run);
        $second = app(RecommendationService::class)->recommend($shift, $run);

        $this->assertSame($first['plan']->orderIds, $second['plan']->orderIds);
        $this->assertSame($first['plan']->sequence, $second['plan']->sequence);
        $this->assertSame(2, Recommendation::query()->count());
        $this->assertSame(OrderStatus::AVAILABLE->value, $shift->shiftOrders()->first()->fresh()->status->value);
        $this->assertTrue(Recommendation::query()->pending()->exists());
        $this->assertTrue($first['recommendation']->selected_order_ids !== []);
        $this->assertNotSame(AgentType::BASELINE->value, $shift->agent_type->value);
    }

    public function test_candidate_limit_returns_a_valid_plan_and_marks_count(): void
    {
        config()->set('courier.routing.provider', 'fallback');
        config()->set('courier.optimization.max_candidates', 1);
        Cache::flush();
        [$run, $shift] = $this->availableDemo();
        $environment = app(StateMapper::class)->toEnvironmentState($run);
        $courier = app(StateMapper::class)->toCourierState($shift, $run);
        $orders = $shift->shiftOrders()->with('order')->available()->get()->pluck('order');

        $plan = app(OptimizationService::class)->optimize($courier, $environment, $orders);

        $this->assertTrue($plan->feasible);
        $this->assertSame(1, $plan->candidatesEvaluated);
    }

    /** @return array{0: SimulationRun, 1: Shift} */
    private function availableDemo(): array
    {
        $shiftService = app(ShiftService::class);
        $run = $shiftService->startRun($shiftService->createRun('demo_normal', 12));
        $run = app(SimulatorService::class)->tick($run, 1, 'optimization-availability');
        $shift = $run->fresh('shifts')->shifts->firstWhere('agent_type', AgentType::COURIER_AI);

        return [$run, $shift];
    }
}
