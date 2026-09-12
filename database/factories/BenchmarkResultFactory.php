<?php

namespace Database\Factories;

use App\Enums\AgentType;
use App\Enums\BenchmarkStatus;
use App\Models\BenchmarkResult;
use App\Models\BenchmarkRun;
use App\Models\SimulationRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BenchmarkResult> */
class BenchmarkResultFactory extends Factory
{
    protected $model = BenchmarkResult::class;

    public function definition(): array
    {
        return [
            'benchmark_run_id' => BenchmarkRun::factory(),
            'simulation_run_id' => SimulationRun::factory(),
            'seed' => 1,
            'agent_type' => AgentType::COURIER_AI,
            'status' => BenchmarkStatus::COMPLETED,
            'gross_earnings_mxn' => '0.00',
            'operating_cost_mxn' => '0.00',
            'net_earnings_mxn' => '0.00',
            'net_hourly_rate_mxn' => '0.00',
            'total_distance_km' => '0.000',
            'deadhead_distance_km' => '0.000',
            'active_minutes' => 0,
            'idle_minutes' => 0,
            'accepted_orders_count' => 0,
            'rejected_orders_count' => 0,
            'completed_orders_count' => 0,
            'late_orders_count' => 0,
            'productive_time_percent' => '0.00',
            'metrics' => ['is_simulated' => true],
            'error_message' => null,
        ];
    }
}
