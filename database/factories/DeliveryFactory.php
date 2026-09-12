<?php

namespace Database\Factories;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\Recommendation;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<Delivery> */
class DeliveryFactory extends Factory
{
    protected $model = Delivery::class;

    public function definition(): array
    {
        return [
            'shift_id' => Shift::factory(),
            'recommendation_id' => Recommendation::factory(),
            'status' => DeliveryStatus::PENDING,
            'route_sequence' => [],
            'order_ids' => [],
            'simulated_started_at' => Carbon::parse('2026-09-12 18:02:00'),
            'simulated_finished_at' => null,
            'gross_earnings_mxn' => '0.00',
            'operating_cost_mxn' => '0.00',
            'net_earnings_mxn' => '0.00',
            'total_distance_km' => '0.000',
            'deadhead_distance_km' => '0.000',
            'simulated_duration_minutes' => '0.00',
            'lateness_minutes' => 0,
            'metadata' => ['is_simulated' => true],
        ];
    }
}
