<?php

namespace Database\Factories;

use App\Enums\ShiftStatus;
use App\Models\SimulationRun;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<SimulationRun> */
class SimulationRunFactory extends Factory
{
    protected $model = SimulationRun::class;

    public function definition(): array
    {
        $startedAt = Carbon::parse('2026-09-12 18:00:00');

        return [
            'scenario_key' => 'demo_normal',
            'seed' => fake()->numberBetween(1, PHP_INT_MAX),
            'status' => ShiftStatus::IDLE,
            'simulated_started_at' => $startedAt,
            'simulated_current_at' => $startedAt,
            'simulated_ends_at' => $startedAt->copy()->addMinutes(120),
            'real_started_at' => null,
            'real_finished_at' => null,
            'real_last_tick_at' => null,
            'traffic_factor' => '1.0000',
            'weather' => 'clear',
            'closure_version' => 0,
            'speed_multiplier' => '1.0000',
            'environment_state' => ['is_simulated' => true],
            'config_snapshot' => ['version' => 'test-v1'],
        ];
    }
}
