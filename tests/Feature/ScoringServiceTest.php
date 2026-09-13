<?php

namespace Tests\Feature;

use App\DTOs\Coordinates;
use App\DTOs\CourierState;
use App\DTOs\EnvironmentState;
use App\DTOs\EvaluatedOrder;
use App\Enums\OrderStatus;
use App\Enums\ReasonCode;
use App\Models\DemandZone;
use App\Models\Order;
use App\Services\RecommendationService;
use App\Services\ScoringService;
use App\Services\ShiftService;
use App\Services\SimulatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScoringServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");
        if ($connection !== 'mysql' || $database !== 'infoSys_testing') {
            throw new \LogicException('Scoring tests may only refresh the dedicated MySQL database infoSys_testing.');
        }
    }

    public function test_economic_formulas_keep_gross_cost_net_and_future_bonus_separate(): void
    {
        Cache::flush();
        DemandZone::factory()->create(['zone_key' => 'CENTRO', 'demand_by_hour' => ['18' => 0.8]]);
        Http::fakeSequence()
            ->push(['code' => 'Ok', 'routes' => [['distance' => 2000, 'duration' => 600, 'geometry' => ['type' => 'LineString', 'coordinates' => [[-100.31, 25.675], [-100.30, 25.68]]]]]])
            ->push(['code' => 'Ok', 'routes' => [['distance' => 3000, 'duration' => 900, 'geometry' => ['type' => 'LineString', 'coordinates' => [[-100.30, 25.68], [-100.29, 25.69]]]]]]);
        $order = Order::factory()->create([
            'base_pay_mxn' => '100.00',
            'surge_bonus_mxn' => '20.00',
            'other_bonus_mxn' => '5.00',
            'estimated_restaurant_wait_min' => 5,
        ]);
        $courier = new CourierState('C1', new Coordinates(25.675, -100.31), 'motorcycle', 120, [], 2, '2.00');
        $environment = new EnvironmentState(new \DateTimeImmutable('2026-09-12 18:00:00'), 'rain', 1.2, [], []);

        $result = app(ScoringService::class)->evaluate($order, $courier, $environment);

        $this->assertSame('125.00', $result->grossPayMxn);
        $this->assertSame(5.0, $result->totalDistanceKm);
        $this->assertSame('10.00', $result->operatingCostMxn);
        $this->assertSame('115.00', $result->netProfitMxn);
        $this->assertGreaterThan(0, (float) $result->netHourlyRateMxn);
        $this->assertSame('16.00', $result->futurePositionBonusMxn);
        $this->assertLessThanOrEqual(1.0, $result->latenessRisk);
        $this->assertGreaterThan($result->restaurantWaitMin, $result->totalTimeMin);
    }

    public function test_rank_normalizes_negative_and_identical_values_and_returns_stable_scores(): void
    {
        config()->set('courier.routing.provider', 'fallback');
        Cache::flush();
        DemandZone::factory()->create(['zone_key' => 'CENTRO', 'demand_by_hour' => ['18' => 0.7]]);
        $orders = Order::factory(3)->create([
            'base_pay_mxn' => '20.00',
            'surge_bonus_mxn' => '0.00',
            'other_bonus_mxn' => '0.00',
            'destination_zone' => 'CENTRO',
        ]);
        $courier = new CourierState('C1', new Coordinates(25.675, -100.31), 'motorcycle', 120, [], 2, '100.00');
        $environment = new EnvironmentState(new \DateTimeImmutable('2026-09-12 18:00:00'), 'clear', 1.0, [], []);

        $ranked = app(ScoringService::class)->rank($orders, $courier, $environment);

        $this->assertCount(3, $ranked);
        foreach ($ranked as $item) {
            $this->assertGreaterThanOrEqual(0, $item->score);
            $this->assertLessThanOrEqual(100, $item->score);
            $this->assertGreaterThanOrEqual(0, $item->destinationDemandScore);
        }
        $again = app(ScoringService::class)->rank($orders, $courier, $environment);
        $this->assertSame(
            array_map(static fn ($item): array => [$item->orderId, $item->score, $item->priority->value], $ranked),
            array_map(static fn ($item): array => [$item->orderId, $item->score, $item->priority->value], $again),
        );
    }

    public function test_hard_deadline_capacity_and_safety_constraints_are_infeasible_with_reason_codes(): void
    {
        config()->set('courier.routing.provider', 'fallback');
        Cache::flush();
        $order = Order::factory()->create([
            'delivery_deadline' => '2026-09-12 17:00:00',
            'restrictions' => ['no_motorcycle'],
        ]);
        $courier = new CourierState('C1', new Coordinates(25.675, -100.31), 'motorcycle', 120, ['ACTIVE-1', 'ACTIVE-2'], 2, '1.25');
        $environment = new EnvironmentState(new \DateTimeImmutable('2026-09-12 18:00:00'), 'clear', 1.0, [], []);

        $result = app(ScoringService::class)->evaluate($order, $courier, $environment);

        $this->assertFalse($result->feasible);
        $this->assertContains(ReasonCode::SHIFT_TOO_SHORT, $result->reasonCodes);
        $this->assertContains(ReasonCode::INCOMPATIBLE_BATCH, $result->reasonCodes);
        $this->assertSame(0.0, $result->score);
    }

    public function test_recommendation_service_only_ranks_available_orders(): void
    {
        config()->set('courier.routing.provider', 'fallback');
        Cache::flush();
        $run = app(ShiftService::class)->createRun('demo_normal', 4);
        $run = app(ShiftService::class)->startRun($run);
        app(SimulatorService::class)->tick($run, 1, 'score-availability');
        $shift = $run->fresh('shifts')->shifts->first();
        $ranked = app(RecommendationService::class)->rankAvailable($shift, $run);

        $this->assertNotEmpty($ranked);
        $this->assertContainsOnlyInstancesOf(EvaluatedOrder::class, $ranked);
        $this->assertTrue(collect($ranked)->every(static fn ($item): bool => $item->score >= 0 && $item->score <= 100));
        $this->assertNotContains(OrderStatus::PENDING->value, $shift->shiftOrders()->pluck('status')->all());
    }
}
