<?php

namespace Database\Factories;

use App\Enums\RecommendationStatus;
use App\Models\Recommendation;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<Recommendation> */
class RecommendationFactory extends Factory
{
    protected $model = Recommendation::class;

    public function definition(): array
    {
        return [
            'shift_id' => Shift::factory(),
            'simulation_time' => Carbon::parse('2026-09-12 18:02:00'),
            'config_version' => 'test-v1',
            'ranking' => [],
            'selected_order_ids' => [],
            'route_sequence' => [],
            'metrics' => ['is_simulated' => true],
            'reason_codes' => [],
            'visual_score' => '0.00',
            'absolute_utility' => '0.00',
            'expected_net_profit_mxn' => '0.00',
            'expected_minutes' => '0.00',
            'expected_distance_km' => '0.000',
            'expected_hourly_rate_mxn' => '0.00',
            'estimated_risk' => '0.000000',
            'status' => RecommendationStatus::PENDING,
            'deterministic_explanation' => null,
            'llm_explanation' => null,
            'routing_duration_ms' => 0,
            'optimization_duration_ms' => 0,
            'candidates_evaluated' => 0,
        ];
    }
}
