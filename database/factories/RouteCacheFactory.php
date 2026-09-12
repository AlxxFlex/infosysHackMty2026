<?php

namespace Database\Factories;

use App\Models\RouteCache;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<RouteCache> */
class RouteCacheFactory extends Factory
{
    protected $model = RouteCache::class;

    public function definition(): array
    {
        return [
            'cache_key' => hash('sha256', fake()->unique()->uuid()),
            'provider' => 'fallback',
            'origin_lat' => '25.6750000',
            'origin_lon' => '-100.3100000',
            'destination_lat' => '25.6860000',
            'destination_lon' => '-100.2960000',
            'traffic_bucket' => 'normal',
            'closure_version' => 0,
            'distance_km' => '5.900',
            'duration_minutes' => '23.00',
            'geometry_geojson' => null,
            'response_summary' => ['is_simulated' => true],
            'expires_at' => Carbon::parse('2026-09-12 18:30:00'),
        ];
    }
}
