<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Coordinates;
use App\DTOs\RouteData;
use App\Models\RouteCache;
use App\Support\Geo;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

final class RoutingService
{
    /**
     * @param  list<Coordinates|array{lat: float|int|string, lon: float|int|string}>  $points
     */
    public function route(
        array $points,
        string|int|float|null $trafficBucket = 'normal',
        int $closureVersion = 0,
        ?string $providerVersion = null,
        ?string $profile = null,
        ?string $algorithmVersion = null,
    ): RouteData {
        $coordinates = $this->normalizePoints($points);
        $trafficBucket = $this->normalizeTrafficBucket($trafficBucket);
        $this->validateContext($trafficBucket, $closureVersion);
        $profile ??= (string) config('courier.routing.profile', 'driving');
        if (trim($profile) === '') {
            throw new \InvalidArgumentException('Routing profile cannot be empty.');
        }
        $providerVersion ??= $algorithmVersion;
        $providerVersion ??= (string) config('courier.routing.algorithm_version', 'v1');
        $provider = (string) config('courier.routing.provider', 'osrm');
        $key = $this->cacheKey($coordinates, $profile, $trafficBucket, $closureVersion, $providerVersion, 'route', $provider);

        if (($cached = Cache::get($this->laravelCacheKey($key))) instanceof RouteData) {
            return $cached;
        }

        $persisted = RouteCache::query()
            ->where('cache_key', $key)
            ->where('expires_at', '>', now())
            ->first();
        if ($persisted !== null) {
            $data = RouteData::fromArray([
                'distance_km' => (float) $persisted->distance_km,
                'duration_minutes' => (float) $persisted->duration_minutes,
                'geometry' => $persisted->geometry_geojson,
                'provider' => $persisted->provider,
                'is_fallback' => ($persisted->response_summary['is_fallback'] ?? false) === true,
                'warnings' => $persisted->response_summary['warnings'] ?? [],
            ]);
            Cache::put($this->laravelCacheKey($key), $data, $this->cacheTtlSeconds());

            return $data;
        }

        $data = $this->fetchRoute($coordinates, $profile, $trafficBucket, $closureVersion, $provider);
        $this->storeRoute($key, $coordinates, $trafficBucket, $closureVersion, $data);
        Cache::put($this->laravelCacheKey($key), $data, $this->cacheTtlSeconds());

        return $data;
    }

    /** Alias kept for callers that prefer an explicit verb. */
    public function getRoute(
        array $points,
        string|int|float|null $trafficBucket = 'normal',
        int $closureVersion = 0,
        ?string $providerVersion = null,
        ?string $profile = null,
        ?string $algorithmVersion = null,
    ): RouteData {
        return $this->route($points, $trafficBucket, $closureVersion, $providerVersion, $profile, $algorithmVersion);
    }

    /**
     * @param  list<Coordinates|array{lat: float|int|string, lon: float|int|string}>  $points
     * @return array{distances_km: list<list<float>>, durations_minutes: list<list<float>>, provider: string, is_fallback: bool, warnings: list<string>}
     */
    public function matrix(
        array $points,
        string|int|float|null $trafficBucket = 'normal',
        int $closureVersion = 0,
        ?string $providerVersion = null,
        ?string $profile = null,
        ?string $algorithmVersion = null,
    ): array {
        $coordinates = $this->normalizePoints($points);
        $trafficBucket = $this->normalizeTrafficBucket($trafficBucket);
        $this->validateContext($trafficBucket, $closureVersion);
        $profile ??= (string) config('courier.routing.profile', 'driving');
        if (trim($profile) === '') {
            throw new \InvalidArgumentException('Routing profile cannot be empty.');
        }
        $providerVersion ??= $algorithmVersion;
        $providerVersion ??= (string) config('courier.routing.algorithm_version', 'v1');
        $provider = (string) config('courier.routing.provider', 'osrm');
        $key = $this->cacheKey($coordinates, $profile, $trafficBucket, $closureVersion, $providerVersion, 'matrix', $provider);
        $cacheKey = $this->laravelCacheKey($key);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $result = $this->fetchMatrix($coordinates, $profile, $provider);
        Cache::put($cacheKey, $result, $this->cacheTtlSeconds());

        return $result;
    }

    /** @return array{distances_km: list<list<float>>, durations_minutes: list<list<float>>, provider: string, is_fallback: bool, warnings: list<string>} */
    public function getMatrix(array $points, string|int|float|null $trafficBucket = 'normal', int $closureVersion = 0, ?string $providerVersion = null, ?string $profile = null, ?string $algorithmVersion = null): array
    {
        return $this->matrix($points, $trafficBucket, $closureVersion, $providerVersion, $profile, $algorithmVersion);
    }

    /** @return list<Coordinates> */
    private function normalizePoints(array $points): array
    {
        if (count($points) < 2) {
            throw new \InvalidArgumentException('Routing requires at least two coordinates.');
        }

        return array_values(array_map(static function (mixed $point): Coordinates {
            if ($point instanceof Coordinates) {
                return $point;
            }
            if (! is_array($point)) {
                throw new \InvalidArgumentException('Routing points must be Coordinates or lat/lon arrays.');
            }

            return Coordinates::fromArray($point);
        }, $points));
    }

    private function validateContext(string $trafficBucket, int $closureVersion): void
    {
        if (trim($trafficBucket) === '') {
            throw new \InvalidArgumentException('Traffic bucket cannot be empty.');
        }
        if ($closureVersion < 0) {
            throw new \InvalidArgumentException('Closure version cannot be negative.');
        }
    }

    private function normalizeTrafficBucket(string|int|float|null $bucket): string
    {
        if ($bucket === null) {
            return 'normal';
        }
        if (is_numeric($bucket)) {
            $size = max(0.0001, (float) config('courier.routing.traffic_bucket_size', 0.10));
            $normalized = floor((float) $bucket / $size) * $size;

            return 'factor_'.number_format($normalized, 2, '.', '');
        }

        return trim($bucket);
    }

    /** @param list<Coordinates> $points */
    private function fetchRoute(array $points, string $profile, string $trafficBucket, int $closureVersion, string $provider): RouteData
    {
        if ($provider === 'fallback') {
            return $this->fallbackRoute($points, 'routing provider explicitly configured as fallback.');
        }

        try {
            $url = $this->osrmUrl('route', $profile, $points);
            $response = $this->http()->get($url, [
                'overview' => 'full',
                'geometries' => 'geojson',
                'steps' => 'false',
            ]);
            if (! $response->successful()) {
                throw new \RuntimeException('HTTP '.$response->status());
            }
            $payload = $response->json();
            $route = is_array($payload) ? ($payload['routes'][0] ?? null) : null;
            if (($payload['code'] ?? null) !== 'Ok' || ! is_array($route)) {
                throw new \RuntimeException('OSRM payload did not contain an Ok route.');
            }
            $distanceMeters = $route['distance'] ?? null;
            $durationSeconds = $route['duration'] ?? null;
            $geometry = $route['geometry'] ?? null;
            if (! is_numeric($distanceMeters) || ! is_numeric($durationSeconds) || (float) $distanceMeters < 0 || (float) $durationSeconds < 0 || ! $this->validGeometry($geometry)) {
                throw new \RuntimeException('OSRM route values or geometry are invalid.');
            }

            return new RouteData(
                distanceKm: (float) $distanceMeters / 1000,
                durationMinutes: (float) $durationSeconds / 60,
                geometry: $geometry,
                provider: 'osrm',
                isFallback: false,
            );
        } catch (Throwable $exception) {
            return $this->fallbackRoute($points, 'OSRM unavailable: '.$this->warningFor($exception));
        }
    }

    /** @param list<Coordinates> $points */
    private function fetchMatrix(array $points, string $profile, string $provider): array
    {
        if ($provider === 'fallback') {
            return $this->fallbackMatrix($points, 'routing provider explicitly configured as fallback.');
        }

        try {
            $url = $this->osrmUrl('table', $profile, $points);
            $response = $this->http()->get($url, ['annotations' => 'distance,duration']);
            if (! $response->successful()) {
                throw new \RuntimeException('HTTP '.$response->status());
            }
            $payload = $response->json();
            $distances = is_array($payload) ? ($payload['distances'] ?? null) : null;
            $durations = is_array($payload) ? ($payload['durations'] ?? null) : null;
            if (($payload['code'] ?? null) !== 'Ok' || ! $this->validMatrix($distances, count($points)) || ! $this->validMatrix($durations, count($points))) {
                throw new \RuntimeException('OSRM table payload is invalid or contains null indices.');
            }

            return [
                'distances_km' => array_map(static fn (array $row): array => array_map(static fn (float|int $value): float => (float) $value / 1000, $row), $distances),
                'durations_minutes' => array_map(static fn (array $row): array => array_map(static fn (float|int $value): float => (float) $value / 60, $row), $durations),
                'provider' => 'osrm',
                'is_fallback' => false,
                'warnings' => [],
            ];
        } catch (Throwable $exception) {
            return $this->fallbackMatrix($points, 'OSRM unavailable: '.$this->warningFor($exception));
        }
    }

    /** @param list<Coordinates> $points */
    private function fallbackRoute(array $points, string $warning): RouteData
    {
        $distance = 0.0;
        for ($index = 1; $index < count($points); $index++) {
            $distance += Geo::haversineKm($points[$index - 1], $points[$index]);
        }
        $distance *= (float) config('courier.routing.road_factor', 1.25);
        $speed = max(1.0, (float) config('courier.routing.fallback_speed_kmh', 30));

        return new RouteData(
            distanceKm: $distance,
            durationMinutes: ($distance / $speed) * 60,
            geometry: Geo::lineString($points),
            provider: 'fallback',
            isFallback: true,
            warnings: [$warning],
        );
    }

    /** @param list<Coordinates> $points */
    private function fallbackMatrix(array $points, string $warning): array
    {
        $size = count($points);
        $distances = [];
        $durations = [];
        $speed = max(1.0, (float) config('courier.routing.fallback_speed_kmh', 30));
        $factor = (float) config('courier.routing.road_factor', 1.25);
        for ($from = 0; $from < $size; $from++) {
            $distanceRow = [];
            $durationRow = [];
            for ($to = 0; $to < $size; $to++) {
                $distance = $from === $to ? 0.0 : Geo::haversineKm($points[$from], $points[$to]) * $factor;
                $distanceRow[] = $distance;
                $durationRow[] = ($distance / $speed) * 60;
            }
            $distances[] = $distanceRow;
            $durations[] = $durationRow;
        }

        return ['distances_km' => $distances, 'durations_minutes' => $durations, 'provider' => 'fallback', 'is_fallback' => true, 'warnings' => [$warning]];
    }

    private function storeRoute(string $key, array $points, string $trafficBucket, int $closureVersion, RouteData $data): void
    {
        $now = CarbonImmutable::now();
        RouteCache::query()->updateOrCreate(
            ['cache_key' => $key],
            [
                'provider' => $data->provider,
                'origin_lat' => $points[0]->lat,
                'origin_lon' => $points[0]->lon,
                'destination_lat' => $points[count($points) - 1]->lat,
                'destination_lon' => $points[count($points) - 1]->lon,
                'traffic_bucket' => $trafficBucket,
                'closure_version' => $closureVersion,
                'distance_km' => number_format($data->distanceKm, 3, '.', ''),
                'duration_minutes' => number_format($data->durationMinutes, 2, '.', ''),
                'geometry_geojson' => $data->geometry,
                'response_summary' => ['is_fallback' => $data->isFallback, 'warnings' => $data->warnings],
                'expires_at' => $now->addSeconds($this->cacheTtlSeconds()),
            ],
        );
    }

    private function cacheTtlSeconds(): int
    {
        return max(1, (int) config('courier.routing.cache_ttl_seconds', 120));
    }

    /** @param list<Coordinates> $points */
    private function cacheKey(array $points, string $profile, string $trafficBucket, int $closureVersion, string $providerVersion, string $kind, string $provider): string
    {
        $serialized = implode(';', array_map(static fn (Coordinates $point): string => number_format($point->lat, 6, '.', '').','.number_format($point->lon, 6, '.', ''), $points));

        return hash('sha256', implode('|', [$kind, $provider, $profile, $trafficBucket, (string) $closureVersion, $providerVersion, $serialized]));
    }

    private function laravelCacheKey(string $key): string
    {
        return 'courier.routing.'.$key;
    }

    /** @param list<Coordinates> $points */
    private function osrmUrl(string $kind, string $profile, array $points): string
    {
        $serialized = implode(';', array_map(static fn (Coordinates $point): string => number_format($point->lon, 7, '.', '').','.number_format($point->lat, 7, '.', ''), $points));

        return rtrim((string) config('courier.routing.base_url', 'https://router.project-osrm.org'), '/').'/'.$kind.'/v1/'.rawurlencode($profile).'/'.$serialized;
    }

    private function http(): PendingRequest
    {
        return Http::timeout(max(1, (int) config('courier.routing.timeout_seconds', 2)))
            ->retry(max(0, (int) config('courier.routing.retries', 2)), max(0, (int) config('courier.routing.retry_sleep_ms', 100)));
    }

    private function validGeometry(mixed $geometry): bool
    {
        if (! is_array($geometry) || ($geometry['type'] ?? null) !== 'LineString' || ! is_array($geometry['coordinates'] ?? null) || count($geometry['coordinates']) < 2) {
            return false;
        }
        foreach ($geometry['coordinates'] as $coordinate) {
            if (! is_array($coordinate) || count($coordinate) < 2 || ! is_numeric($coordinate[0]) || ! is_numeric($coordinate[1])) {
                return false;
            }
        }

        return true;
    }

    private function validMatrix(mixed $matrix, int $size): bool
    {
        if (! is_array($matrix) || count($matrix) !== $size) {
            return false;
        }
        foreach ($matrix as $row) {
            if (! is_array($row) || count($row) !== $size) {
                return false;
            }
            foreach ($row as $value) {
                if (! is_numeric($value) || (float) $value < 0) {
                    return false;
                }
            }
        }

        return true;
    }

    private function warningFor(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof RequestException => 'http_'.$exception->response->status(),
            default => str_replace(["\n", "\r"], ' ', $exception->getMessage()),
        };
    }
}
