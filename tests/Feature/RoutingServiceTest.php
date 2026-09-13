<?php

namespace Tests\Feature;

use App\DTOs\Coordinates;
use App\Services\RoutingService;
use App\Support\Geo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RoutingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");
        if ($connection !== 'mysql' || $database !== 'infoSys_testing') {
            throw new \LogicException('Routing tests may only refresh the dedicated MySQL database infoSys_testing.');
        }
    }

    public function test_osrm_route_uses_lon_lat_and_returns_uniform_route_data(): void
    {
        Cache::flush();
        Http::fake(function (Request $request) {
            $this->assertStringContainsString('/route/v1/driving/-100.3100000,25.6750000;-100.2960000,25.6860000', $request->url());

            return Http::response([
                'code' => 'Ok',
                'routes' => [[
                    'distance' => 5900,
                    'duration' => 1380,
                    'geometry' => ['type' => 'LineString', 'coordinates' => [[-100.31, 25.675], [-100.296, 25.686]]],
                ]],
            ]);
        });

        $route = app(RoutingService::class)->route([
            new Coordinates(25.675, -100.31),
            new Coordinates(25.686, -100.296),
        ]);

        $this->assertSame(5.9, $route->distanceKm);
        $this->assertSame(23.0, $route->durationMinutes);
        $this->assertSame('osrm', $route->provider);
        $this->assertFalse($route->isFallback);
        $this->assertSame('LineString', $route->geometry['type']);
        $this->assertDatabaseHas('route_caches', ['provider' => 'osrm', 'distance_km' => '5.900']);
    }

    public function test_osrm_table_returns_kilometres_and_minutes(): void
    {
        Cache::flush();
        Http::fake(['*/table/v1/driving/*' => Http::response([
            'code' => 'Ok',
            'distances' => [[0, 1000], [1200, 0]],
            'durations' => [[0, 60], [72, 0]],
        ])]);

        $matrix = app(RoutingService::class)->matrix([
            ['lat' => 25.675, 'lon' => -100.31],
            ['lat' => 25.686, 'lon' => -100.296],
        ]);

        $this->assertSame([[0.0, 1.0], [1.2, 0.0]], $matrix['distances_km']);
        $this->assertSame([[0.0, 1.0], [1.2, 0.0]], $matrix['durations_minutes']);
        $this->assertSame('osrm', $matrix['provider']);
        $this->assertFalse($matrix['is_fallback']);
    }

    public function test_cache_hit_avoids_a_second_osrm_request_and_context_changes_make_new_keys(): void
    {
        Cache::flush();
        Http::fake(['*/route/v1/driving/*' => Http::response([
            'code' => 'Ok',
            'routes' => [['distance' => 1000, 'duration' => 60, 'geometry' => ['type' => 'LineString', 'coordinates' => [[-100.31, 25.675], [-100.3, 25.68]]]]],
        ])]);
        $service = app(RoutingService::class);
        $points = [new Coordinates(25.675, -100.31), new Coordinates(25.68, -100.3)];

        $service->route($points, 'bucket-a', 0);
        $service->route($points, 'bucket-a', 0);
        $service->route($points, 'bucket-b', 0);
        $service->route($points, 'bucket-a', 1);

        Http::assertSentCount(3);
    }

    public function test_timeout_5xx_and_invalid_payload_use_deterministic_fallback(): void
    {
        Cache::flush();
        Http::fakeSequence()->pushStatus(503)->push(['code' => 'Invalid', 'routes' => []]);
        $service = app(RoutingService::class);
        $points = [new Coordinates(25.675, -100.31), new Coordinates(25.686, -100.296)];
        $first = $service->route($points, 'failure-a');
        $second = $service->route($points, 'failure-b');

        $this->assertTrue($first->isFallback);
        $this->assertSame('fallback', $first->provider);
        $this->assertNotEmpty($first->warnings);
        $this->assertStringContainsString('OSRM unavailable', $first->warnings[0]);
        $this->assertTrue($second->isFallback);
    }

    public function test_haversine_and_fallback_matrix_are_symmetric_with_zero_diagonal(): void
    {
        $a = new Coordinates(25.675, -100.31);
        $b = new Coordinates(25.686, -100.296);
        $this->assertSame(Geo::haversineKm($a, $b), Geo::haversineKm($b, $a));
        $this->assertSame(0.0, Geo::haversineKm($a, $a));

        config()->set('courier.routing.provider', 'fallback');
        $matrix = app(RoutingService::class)->matrix([$a, $b], 'fallback-matrix');
        $this->assertSame(0.0, $matrix['distances_km'][0][0]);
        $this->assertSame(0.0, $matrix['distances_km'][1][1]);
        $this->assertSame($matrix['distances_km'][0][1], $matrix['distances_km'][1][0]);
        $this->assertTrue($matrix['is_fallback']);
    }
}
