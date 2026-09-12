<?php

namespace Database\Factories;

use App\Enums\AgentType;
use App\Enums\CourierStatus;
use App\Enums\ShiftStatus;
use App\Models\Shift;
use App\Models\SimulationRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Shift> */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    public function definition(): array
    {
        return [
            'simulation_run_id' => SimulationRun::factory(),
            'agent_type' => AgentType::COURIER_AI,
            'status' => ShiftStatus::IDLE,
            'courier_status' => CourierStatus::AVAILABLE,
            'initial_lat' => '25.6750000',
            'initial_lon' => '-100.3100000',
            'current_lat' => '25.6750000',
            'current_lon' => '-100.3100000',
            'max_concurrent_orders' => 2,
            'cost_per_km_mxn' => '1.25',
            'gross_earnings_mxn' => '0.00',
            'net_earnings_mxn' => '0.00',
            'operating_cost_mxn' => '0.00',
            'total_distance_km' => '0.000',
            'deadhead_distance_km' => '0.000',
            'active_minutes' => 0,
            'idle_minutes' => 0,
            'accepted_orders_count' => 0,
            'rejected_orders_count' => 0,
            'completed_orders_count' => 0,
            'late_orders_count' => 0,
        ];
    }
}
