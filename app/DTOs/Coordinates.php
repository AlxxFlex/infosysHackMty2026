<?php

declare(strict_types=1);

namespace App\DTOs;

use InvalidArgumentException;

final readonly class Coordinates
{
    public function __construct(
        public float $lat,
        public float $lon,
    ) {
        if (! is_finite($this->lat) || $this->lat < -90 || $this->lat > 90) {
            throw new InvalidArgumentException('Latitude must be a finite value between -90 and 90.');
        }

        if (! is_finite($this->lon) || $this->lon < -180 || $this->lon > 180) {
            throw new InvalidArgumentException('Longitude must be a finite value between -180 and 180.');
        }
    }

    /** @param array{lat: float|int|string, lon: float|int|string} $value */
    public static function fromArray(array $value): self
    {
        if (! array_key_exists('lat', $value) || ! array_key_exists('lon', $value)) {
            throw new InvalidArgumentException('Coordinates require lat and lon.');
        }

        if (! is_int($value['lat']) && ! is_float($value['lat']) && ! is_string($value['lat'])) {
            throw new InvalidArgumentException('Latitude and longitude must be numeric.');
        }

        if (! is_int($value['lon']) && ! is_float($value['lon']) && ! is_string($value['lon'])) {
            throw new InvalidArgumentException('Latitude and longitude must be numeric.');
        }

        if (! is_numeric($value['lat']) || ! is_numeric($value['lon'])) {
            throw new InvalidArgumentException('Latitude and longitude must be numeric.');
        }

        return new self((float) $value['lat'], (float) $value['lon']);
    }

    /** @return array{lat: float, lon: float} */
    public function toArray(): array
    {
        return ['lat' => $this->lat, 'lon' => $this->lon];
    }
}
