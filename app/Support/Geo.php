<?php

declare(strict_types=1);

namespace App\Support;

use App\DTOs\Coordinates;

final class Geo
{
    public const EARTH_RADIUS_KM = 6371.0088;

    public static function haversineKm(Coordinates $from, Coordinates $to): float
    {
        $latDelta = deg2rad($to->lat - $from->lat);
        $lonDelta = deg2rad($to->lon - $from->lon);
        $fromLat = deg2rad($from->lat);
        $toLat = deg2rad($to->lat);
        $a = sin($latDelta / 2) ** 2
            + cos($fromLat) * cos($toLat) * sin($lonDelta / 2) ** 2;
        $a = min(1.0, max(0.0, $a));

        return self::EARTH_RADIUS_KM * 2 * asin(sqrt($a));
    }

    /** @return array{type: 'LineString', coordinates: list<array{float, float}>} */
    public static function lineString(array $points): array
    {
        return [
            'type' => 'LineString',
            'coordinates' => array_map(
                static fn (Coordinates $point): array => [$point->lon, $point->lat],
                $points,
            ),
        ];
    }
}
