<?php

declare(strict_types=1);

namespace App\DTOs;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final readonly class EnvironmentState
{
    /**
     * @param  list<array<string, mixed>>  $surgeZones
     * @param  list<array<string, mixed>>  $roadClosures
     */
    public function __construct(
        public DateTimeImmutable $simulationTime,
        public string $weather,
        public float $trafficFactor,
        public array $surgeZones,
        public array $roadClosures,
        public int $closureVersion = 0,
    ) {
        if ($this->weather === '') {
            throw new InvalidArgumentException('Weather cannot be empty.');
        }

        if (! is_finite($this->trafficFactor) || $this->trafficFactor <= 0) {
            throw new InvalidArgumentException('Traffic factor must be a finite value greater than zero.');
        }

        if ($this->closureVersion < 0) {
            throw new InvalidArgumentException('Closure version cannot be negative.');
        }

        foreach ([$this->surgeZones, $this->roadClosures] as $items) {
            if (! array_is_list($items)) {
                throw new InvalidArgumentException('Environment collections must be lists.');
            }

            foreach ($items as $item) {
                if (! is_array($item)) {
                    throw new InvalidArgumentException('Environment entries must be arrays.');
                }
            }
        }
    }

    /** @param array<string, mixed> $value */
    public static function fromArray(array $value): self
    {
        $time = $value['simulation_time'] ?? null;

        if (! is_string($time) && ! $time instanceof DateTimeInterface) {
            throw new InvalidArgumentException('Environment state requires a simulation_time.');
        }

        return new self(
            simulationTime: $time instanceof DateTimeInterface
                ? DateTimeImmutable::createFromInterface($time)
                : new DateTimeImmutable($time),
            weather: (string) ($value['weather'] ?? ''),
            trafficFactor: (float) ($value['traffic_factor'] ?? 0),
            surgeZones: array_values((array) ($value['surge_zones'] ?? [])),
            roadClosures: array_values((array) ($value['road_closures'] ?? [])),
            closureVersion: (int) ($value['closure_version'] ?? 0),
        );
    }

    /** @return array{simulation_time: string, weather: string, traffic_factor: float, surge_zones: list<array<string, mixed>>, road_closures: list<array<string, mixed>>, closure_version: int} */
    public function toArray(): array
    {
        return [
            'simulation_time' => $this->simulationTime->format(DateTimeInterface::ATOM),
            'weather' => $this->weather,
            'traffic_factor' => $this->trafficFactor,
            'surge_zones' => $this->surgeZones,
            'road_closures' => $this->roadClosures,
            'closure_version' => $this->closureVersion,
        ];
    }
}
