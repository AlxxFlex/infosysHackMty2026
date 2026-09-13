<?php

namespace Tests\Unit;

use App\Services\ShiftService;
use App\Support\MapPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapPresenterTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");
        if ($connection !== 'mysql' || $database !== 'infoSys_testing') {
            throw new \LogicException('Map tests may only refresh the dedicated MySQL database infoSys_testing.');
        }
    }

    public function test_presenter_produces_valid_geojson_with_stable_features_and_fallback_label(): void
    {
        $run = app(ShiftService::class)->createRun('demo_normal', 77);
        $shift = $run->shifts()->firstOrFail();
        $payload = app(MapPresenter::class)->present($shift, ['plan' => ['sequence' => [['action' => 'PICKUP', 'order_id' => $run->orders()->first()->external_id], ['action' => 'DROPOFF', 'order_id' => $run->orders()->first()->external_id]]]]);

        $this->assertSame('FeatureCollection', $payload['type']);
        $this->assertNotEmpty($payload['features']);
        $this->assertSame('fallback', $payload['route']['provider']);
        $route = collect($payload['features'])->firstWhere('properties.kind', 'recommended-route');
        $this->assertNotNull($route);
        $this->assertSame('LineString', $route['geometry']['type']);
        $this->assertTrue($route['properties']['is_fallback']);
        $this->assertSame('courier-'.$shift->id, $payload['features'][0]['id']);
    }
}
