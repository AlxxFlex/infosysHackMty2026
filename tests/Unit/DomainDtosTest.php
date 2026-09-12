<?php

namespace Tests\Unit;

use App\DTOs\Coordinates;
use App\DTOs\CourierState;
use App\DTOs\EnvironmentState;
use App\DTOs\EvaluatedOrder;
use App\DTOs\OptimizedPlan;
use App\DTOs\RouteData;
use App\Enums\Priority;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DomainDtosTest extends TestCase
{
    public function test_coordinates_validate_ranges_and_serialize(): void
    {
        $coordinates = Coordinates::fromArray(['lat' => '25.675', 'lon' => '-100.31']);

        $this->assertSame(['lat' => 25.675, 'lon' => -100.31], $coordinates->toArray());
        $this->expectException(InvalidArgumentException::class);
        new Coordinates(91.0, 0.0);
    }

    public function test_coordinates_reject_non_numeric_input(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Coordinates::fromArray(['lat' => 'not-a-latitude', 'lon' => 0]);
    }

    public function test_courier_state_rejects_negative_time_and_capacity_overflow(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CourierState(
            courierId: 'SIM-001',
            position: new Coordinates(25.675, -100.31),
            vehicle: 'motorcycle',
            shiftRemainingMin: 60,
            currentOrders: ['ORD-001', 'ORD-002'],
            maxConcurrentOrders: 1,
            costPerKmMxn: '1.25',
        );
    }

    public function test_environment_state_requires_positive_traffic_factor(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EnvironmentState::fromArray([
            'simulation_time' => '2026-09-12T18:00:00-06:00',
            'weather' => 'clear',
            'traffic_factor' => 0,
        ]);
    }

    public function test_route_data_accepts_fallback_line_string_and_rejects_invalid_geometry(): void
    {
        $route = new RouteData(
            distanceKm: 5.9,
            durationMinutes: 23.0,
            geometry: ['type' => 'LineString', 'coordinates' => [[-100.31, 25.675], [-100.296, 25.686]]],
            provider: 'fallback',
            isFallback: true,
            warnings: ['Estimated road distance'],
        );

        $this->assertTrue($route->toArray()['is_fallback']);

        $this->expectException(InvalidArgumentException::class);
        new RouteData(1.0, 2.0, ['type' => 'Point'], 'osrm', false);
    }

    public function test_evaluated_order_serializes_reason_codes_and_enforces_invariants(): void
    {
        $order = EvaluatedOrder::fromArray([
            'order_id' => 'ORD-001',
            'gross_pay_mxn' => '98.00',
            'deadhead_distance_km' => 1.1,
            'delivery_distance_km' => 4.8,
            'total_distance_km' => 5.9,
            'travel_time_min' => 18,
            'restaurant_wait_min' => 5,
            'total_time_min' => 23,
            'operating_cost_mxn' => '7.38',
            'net_profit_mxn' => '90.62',
            'net_hourly_rate_mxn' => '236.40',
            'destination_demand_score' => 0.82,
            'lateness_risk' => 0.08,
            'batch_compatibility' => 0.73,
            'score' => 91.2,
            'priority' => 'HIGH',
            'feasible' => true,
            'reason_codes' => ['HIGH_HOURLY_RATE', 'SHIFT_FIT'],
        ]);

        $this->assertSame('HIGH', $order->toArray()['priority']);
        $this->assertSame(['HIGH_HOURLY_RATE', 'SHIFT_FIT'], $order->toArray()['reason_codes']);

        $this->expectException(InvalidArgumentException::class);
        new EvaluatedOrder(
            'ORD-002', '10.00', 0, 1, 1, 1, 0, 1, '1.00', '11.00', '660.00',
            0.5, 0.1, 0.2, 50, Priority::MEDIUM, true,
        );
    }

    public function test_optimized_plan_keeps_absolute_utility_separate_from_expected_net(): void
    {
        $plan = OptimizedPlan::fromArray([
            'feasible' => true,
            'orders' => ['ORD-001', 'ORD-004'],
            'sequence' => ['PICKUP:ORD-001', 'PICKUP:ORD-004', 'DROPOFF:ORD-001', 'DROPOFF:ORD-004'],
            'expected_net_profit_mxn' => '164.00',
            'future_position_value_mxn' => '16.40',
            'batch_efficiency_bonus_mxn' => '9.00',
            'expected_delay_cost_mxn' => '2.00',
            'risk_penalty_mxn' => '1.00',
            'idle_penalty_mxn' => '0.00',
            'absolute_utility' => '186.40',
            'expected_minutes' => 42,
            'expected_distance_km' => 9.2,
            'net_hourly_rate_mxn' => '234.29',
            'risk' => 0.09,
            'reason_codes' => ['BATCH_COMPATIBLE'],
            'route_provider' => 'fallback',
            'routing_fallback' => true,
        ]);

        $this->assertSame('164.00', $plan->expectedNetProfitMxn);
        $this->assertSame('186.40', $plan->absoluteUtilityMxn);
        $this->assertSame(['BATCH_COMPATIBLE'], $plan->toArray()['reason_codes']);
    }

    public function test_valid_dto_round_trips_dates_and_nested_values(): void
    {
        $state = EnvironmentState::fromArray([
            'simulation_time' => new DateTimeImmutable('2026-09-12T18:00:00-06:00'),
            'weather' => 'clear',
            'traffic_factor' => 1.2,
            'surge_zones' => [['zone' => 'CENTRO', 'multiplier' => 1.3]],
            'road_closures' => [],
            'closure_version' => 2,
        ]);

        $this->assertSame('2026-09-12T18:00:00-06:00', $state->toArray()['simulation_time']);
    }
}
