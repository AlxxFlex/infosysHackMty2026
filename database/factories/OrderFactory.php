<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\SimulationRun;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $spawn = Carbon::parse('2026-09-12 18:00:00');

        return [
            'simulation_run_id' => SimulationRun::factory(),
            'external_id' => 'ORD-'.$this->faker->unique()->numerify('###'),
            'scenario_order_key' => 'demo-order-'.$this->faker->unique()->numerify('###'),
            'spawn_time' => $spawn,
            'expires_at' => $spawn->copy()->addMinutes(5),
            'pickup_deadline' => $spawn->copy()->addMinutes(20),
            'delivery_deadline' => $spawn->copy()->addMinutes(45),
            'restaurant_id' => 'REST-001',
            'restaurant_name' => 'Demo Restaurant',
            'restaurant_lat' => '25.6710000',
            'restaurant_lon' => '-100.3090000',
            'customer_lat' => '25.6860000',
            'customer_lon' => '-100.2960000',
            'destination_zone' => 'CENTRO',
            'base_pay_mxn' => '78.00',
            'surge_bonus_mxn' => '20.00',
            'other_bonus_mxn' => '0.00',
            'estimated_restaurant_wait_min' => 5,
            'restrictions' => [],
            'metadata' => ['is_simulated' => true],
        ];
    }
}
