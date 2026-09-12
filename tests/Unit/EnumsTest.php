<?php

namespace Tests\Unit;

use App\Enums\AgentType;
use App\Enums\BenchmarkStatus;
use App\Enums\CourierStatus;
use App\Enums\DeliveryStatus;
use App\Enums\EventType;
use App\Enums\OrderStatus;
use App\Enums\Priority;
use App\Enums\ReasonCode;
use App\Enums\RecommendationStatus;
use App\Enums\ShiftStatus;
use PHPUnit\Framework\TestCase;

class EnumsTest extends TestCase
{
    public function test_all_persisted_enum_values_are_stable_strings(): void
    {
        $this->assertSame(['COURIER_AI', 'BASELINE'], array_column(AgentType::cases(), 'value'));
        $this->assertSame(['PENDING', 'RUNNING', 'COMPLETED', 'FAILED'], array_column(BenchmarkStatus::cases(), 'value'));
        $this->assertSame(['IDLE', 'RUNNING', 'PAUSED', 'FINISHED'], array_column(ShiftStatus::cases(), 'value'));
        $this->assertSame(
            ['AVAILABLE', 'GOING_TO_PICKUP', 'WAITING_AT_RESTAURANT', 'DELIVERING'],
            array_column(CourierStatus::cases(), 'value')
        );
        $this->assertSame(
            ['PENDING', 'AVAILABLE', 'RECOMMENDED', 'ACCEPTED', 'PICKING_UP', 'PICKED_UP', 'DELIVERING', 'DELIVERED', 'EXPIRED', 'REJECTED'],
            array_column(OrderStatus::cases(), 'value')
        );
        $this->assertSame(['HIGH', 'MEDIUM', 'LOW'], array_column(Priority::cases(), 'value'));
        $this->assertSame(['PENDING', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'], array_column(DeliveryStatus::cases(), 'value'));
        $this->assertSame(['PENDING', 'ACCEPTED', 'SUPERSEDED', 'REJECTED'], array_column(RecommendationStatus::cases(), 'value'));
        $this->assertSame(
            ['NEW_ORDER', 'ORDER_EXPIRED', 'SURGE_STARTED', 'SURGE_ENDED', 'TRAFFIC_CHANGED', 'ROAD_CLOSED', 'ROAD_REOPENED', 'WEATHER_CHANGED', 'RESTAURANT_DELAY_CHANGED', 'ORDER_ACCEPTED', 'PICKUP_COMPLETED', 'DELIVERY_COMPLETED'],
            array_column(EventType::cases(), 'value')
        );
        $this->assertSame(
            ['HIGH_HOURLY_RATE', 'HIGH_NET_PROFIT', 'SHORT_PICKUP', 'LOW_DEADHEAD', 'HIGH_DEMAND_DESTINATION', 'BATCH_COMPATIBLE', 'LOW_DELAY_RISK', 'SURGE_ADVANTAGE', 'SHIFT_FIT', 'LOW_HOURLY_RATE', 'LONG_PICKUP', 'LOW_DEMAND_DESTINATION', 'HIGH_DELAY_RISK', 'TRAFFIC_PENALTY', 'ROAD_CLOSURE', 'SHIFT_TOO_SHORT', 'INCOMPATIBLE_BATCH'],
            array_column(ReasonCode::cases(), 'value')
        );
    }

    public function test_enums_round_trip_from_persisted_values(): void
    {
        foreach ([...AgentType::cases(), ...BenchmarkStatus::cases(), ...ShiftStatus::cases(), ...CourierStatus::cases(), ...OrderStatus::cases(), ...EventType::cases(), ...Priority::cases(), ...RecommendationStatus::cases(), ...DeliveryStatus::cases(), ...ReasonCode::cases()] as $case) {
            $this->assertSame($case, $case::from($case->value));
        }
    }
}
