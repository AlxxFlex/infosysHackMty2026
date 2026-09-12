<?php

namespace Database\Factories;

use App\Enums\BenchmarkStatus;
use App\Models\BenchmarkRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BenchmarkRun> */
class BenchmarkRunFactory extends Factory
{
    protected $model = BenchmarkRun::class;

    public function definition(): array
    {
        return [
            'scenario_key' => 'demo_normal',
            'seeds' => [1, 2, 3],
            'config_snapshot' => ['version' => 'test-v1'],
            'config_version' => 'test-v1',
            'algorithm_version' => 'test-algorithm-v1',
            'status' => BenchmarkStatus::PENDING,
            'total_seeds' => 3,
            'completed_seeds' => 0,
            'failed_seeds' => 0,
            'started_at' => null,
            'finished_at' => null,
        ];
    }
}
