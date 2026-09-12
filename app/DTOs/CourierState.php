<?php

declare(strict_types=1);

namespace App\DTOs;

use InvalidArgumentException;

final readonly class CourierState
{
    /**
     * @param  list<string>  $currentOrders
     */
    public function __construct(
        public string $courierId,
        public Coordinates $position,
        public string $vehicle,
        public int $shiftRemainingMin,
        public array $currentOrders,
        public int $maxConcurrentOrders,
        public string $costPerKmMxn,
    ) {
        if ($this->courierId === '' || $this->vehicle === '') {
            throw new InvalidArgumentException('Courier id and vehicle cannot be empty.');
        }

        if ($this->shiftRemainingMin < 0) {
            throw new InvalidArgumentException('Shift remaining minutes cannot be negative.');
        }

        if ($this->maxConcurrentOrders < 0 || count($this->currentOrders) > $this->maxConcurrentOrders) {
            throw new InvalidArgumentException('Current orders exceed the configured capacity.');
        }

        foreach ($this->currentOrders as $orderId) {
            if (! is_string($orderId) || $orderId === '') {
                throw new InvalidArgumentException('Current order ids cannot be empty.');
            }
        }

        if (count($this->currentOrders) !== count(array_unique($this->currentOrders))) {
            throw new InvalidArgumentException('Current order ids must be unique.');
        }

        self::assertDecimal($this->costPerKmMxn, 'Cost per kilometre');

        if ((float) $this->costPerKmMxn < 0) {
            throw new InvalidArgumentException('Cost per kilometre cannot be negative.');
        }
    }

    /** @param array<string, mixed> $value */
    public static function fromArray(array $value): self
    {
        return new self(
            courierId: (string) self::required($value, 'courier_id'),
            position: Coordinates::fromArray((array) self::required($value, 'position')),
            vehicle: (string) self::required($value, 'vehicle'),
            shiftRemainingMin: (int) self::required($value, 'shift_remaining_min'),
            currentOrders: array_values((array) ($value['current_orders'] ?? [])),
            maxConcurrentOrders: (int) self::required($value, 'max_concurrent_orders'),
            costPerKmMxn: (string) self::required($value, 'cost_per_km_mxn'),
        );
    }

    /** @return array{courier_id: string, position: array{lat: float, lon: float}, vehicle: string, shift_remaining_min: int, current_orders: list<string>, max_concurrent_orders: int, cost_per_km_mxn: string} */
    public function toArray(): array
    {
        return [
            'courier_id' => $this->courierId,
            'position' => $this->position->toArray(),
            'vehicle' => $this->vehicle,
            'shift_remaining_min' => $this->shiftRemainingMin,
            'current_orders' => $this->currentOrders,
            'max_concurrent_orders' => $this->maxConcurrentOrders,
            'cost_per_km_mxn' => $this->costPerKmMxn,
        ];
    }

    private static function required(array $value, string $key): mixed
    {
        if (! array_key_exists($key, $value)) {
            throw new InvalidArgumentException("Courier state requires {$key}.");
        }

        return $value[$key];
    }

    private static function assertDecimal(string $value, string $label): void
    {
        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException("{$label} must be a decimal string.");
        }
    }
}
