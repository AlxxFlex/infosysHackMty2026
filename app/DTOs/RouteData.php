<?php

declare(strict_types=1);

namespace App\DTOs;

use InvalidArgumentException;

final readonly class RouteData
{
    /** @param array<string, mixed>|null $geometry */
    public function __construct(
        public float $distanceKm,
        public float $durationMinutes,
        public ?array $geometry,
        public string $provider,
        public bool $isFallback,
        /** @var list<string> */
        public array $warnings = [],
    ) {
        if (! is_finite($this->distanceKm) || $this->distanceKm < 0) {
            throw new InvalidArgumentException('Route distance cannot be negative or non-finite.');
        }

        if (! is_finite($this->durationMinutes) || $this->durationMinutes < 0) {
            throw new InvalidArgumentException('Route duration cannot be negative or non-finite.');
        }

        if ($this->provider === '') {
            throw new InvalidArgumentException('Route provider cannot be empty.');
        }

        foreach ($this->warnings as $warning) {
            if ($warning === '') {
                throw new InvalidArgumentException('Route warnings cannot be empty.');
            }
        }

        if ($this->geometry !== null && ($this->geometry['type'] ?? null) !== 'LineString') {
            throw new InvalidArgumentException('Route geometry must be a GeoJSON LineString.');
        }
    }

    /** @param array<string, mixed> $value */
    public static function fromArray(array $value): self
    {
        return new self(
            distanceKm: (float) ($value['distance_km'] ?? $value['distanceKm'] ?? 0),
            durationMinutes: (float) ($value['duration_minutes'] ?? $value['durationMinutes'] ?? 0),
            geometry: isset($value['geometry']) && is_array($value['geometry']) ? $value['geometry'] : null,
            provider: (string) ($value['provider'] ?? ''),
            isFallback: (bool) ($value['is_fallback'] ?? $value['isFallback'] ?? false),
            warnings: array_values((array) ($value['warnings'] ?? [])),
        );
    }

    /** @return array{distance_km: float, duration_minutes: float, geometry: array<string, mixed>|null, provider: string, is_fallback: bool, warnings: list<string>} */
    public function toArray(): array
    {
        return [
            'distance_km' => $this->distanceKm,
            'duration_minutes' => $this->durationMinutes,
            'geometry' => $this->geometry,
            'provider' => $this->provider,
            'is_fallback' => $this->isFallback,
            'warnings' => $this->warnings,
        ];
    }
}
