<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Coordinates;
use App\Enums\EventType;
use App\Enums\OrderStatus;
use App\Events\SimulationUpdated;
use App\Exceptions\InvalidScenarioDefinition;
use App\Models\ShiftOrder;
use App\Models\SimulationRun;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

final class ScenarioService
{
    /** @return array<string, mixed> */
    public function load(string $scenarioKey, int $seed = 0): array
    {
        if ($scenarioKey === 'challenge' || $scenarioKey === 'challenge_mode') {
            $scenario = $this->generateChallenge($seed);
        } else {
            if (! preg_match('/^[a-z0-9_-]+$/', $scenarioKey)) {
                throw new InvalidScenarioDefinition('Scenario key may contain only lowercase letters, numbers, underscores and hyphens.');
            }

            $path = database_path('data/scenarios/'.$scenarioKey.'.json');

            if (! File::exists($path)) {
                throw new InvalidScenarioDefinition("Scenario file not found: {$scenarioKey}.");
            }

            try {
                $scenario = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new InvalidScenarioDefinition("Scenario {$scenarioKey} is not valid JSON: {$exception->getMessage()}", previous: $exception);
            }
        }

        if (! is_array($scenario)) {
            throw new InvalidScenarioDefinition('Scenario root must be an object.');
        }

        $this->validate($scenario, $scenarioKey);

        return $scenario;
    }

    /** @param array<string, mixed> $scenario */
    public function validate(array $scenario, ?string $expectedKey = null): void
    {
        foreach (['scenario_key', 'version', 'timezone', 'duration_min', 'initial_position', 'metadata', 'orders', 'events'] as $field) {
            if (! array_key_exists($field, $scenario)) {
                throw new InvalidScenarioDefinition("Missing required scenario field: {$field}.");
            }
        }

        if (! is_string($scenario['scenario_key']) || $scenario['scenario_key'] === '') {
            throw new InvalidScenarioDefinition('scenario_key must be a non-empty string.');
        }

        if ($expectedKey !== null && ! in_array($expectedKey, ['challenge', 'challenge_mode'], true) && $scenario['scenario_key'] !== $expectedKey) {
            throw new InvalidScenarioDefinition('scenario_key does not match the requested scenario.');
        }

        if (! is_int($scenario['version']) || $scenario['version'] < 1) {
            throw new InvalidScenarioDefinition('version must be a positive integer.');
        }

        if (! is_string($scenario['timezone']) || ! in_array($scenario['timezone'], timezone_identifiers_list(), true)) {
            throw new InvalidScenarioDefinition('timezone must be a valid IANA timezone.');
        }

        if (! is_int($scenario['duration_min']) || $scenario['duration_min'] <= 0) {
            throw new InvalidScenarioDefinition('duration_min must be a positive integer.');
        }

        try {
            Coordinates::fromArray($scenario['initial_position']);
        } catch (\Throwable $exception) {
            throw new InvalidScenarioDefinition('initial_position must contain valid lat/lon coordinates.', previous: $exception);
        }

        $metadata = $scenario['metadata'];
        if (! is_array($metadata) || ($metadata['is_simulated'] ?? null) !== true) {
            throw new InvalidScenarioDefinition('metadata.is_simulated must be true.');
        }
        foreach (['payments', 'demand', 'surge', 'traffic'] as $field) {
            if (($metadata[$field] ?? null) !== 'simulated') {
                throw new InvalidScenarioDefinition("metadata.{$field} must be explicitly labelled simulated.");
            }
        }

        if (! is_array($scenario['orders']) || count($scenario['orders']) === 0) {
            throw new InvalidScenarioDefinition('orders must contain at least one order.');
        }
        if (! is_array($scenario['events'])) {
            throw new InvalidScenarioDefinition('events must be an array.');
        }

        $orderKeys = [];
        $externalIds = [];
        foreach ($scenario['orders'] as $index => $order) {
            $this->validateOrder($order, $index, (int) $scenario['duration_min'], $orderKeys, $externalIds);
        }

        foreach ($scenario['events'] as $index => $event) {
            if (! is_array($event)) {
                throw new InvalidScenarioDefinition("events[{$index}] must be an object.");
            }
            if (! is_string($event['type'] ?? null) || EventType::tryFrom($event['type']) === null) {
                throw new InvalidScenarioDefinition("events[{$index}].type must be a supported event type.");
            }
            if (! is_int($event['scheduled_minute'] ?? null) || $event['scheduled_minute'] < 0 || $event['scheduled_minute'] > $scenario['duration_min']) {
                throw new InvalidScenarioDefinition("events[{$index}].scheduled_minute is outside the scenario duration.");
            }
            if (! is_int($event['sequence'] ?? null) || $event['sequence'] < 0) {
                throw new InvalidScenarioDefinition("events[{$index}].sequence must be a non-negative integer.");
            }
            if (! is_array($event['payload'] ?? null) || (($event['payload']['is_simulated'] ?? true) !== true)) {
                throw new InvalidScenarioDefinition("events[{$index}].payload must be an array labelled simulated.");
            }
        }
    }

    /** @param array<string, mixed> $scenario */
    public function materialize(SimulationRun $run, array $scenario): void
    {
        $this->validate($scenario, $run->scenario_key);
        $shifts = $run->shifts()->lockForUpdate()->get();
        $start = CarbonImmutable::instance($run->simulated_started_at);
        $sharedMetadata = $scenario['metadata'];

        foreach ($scenario['orders'] as $definition) {
            $spawnAt = $start->addMinutes($definition['spawn_minute']);
            $order = $run->orders()->create([
                'external_id' => $definition['external_id'],
                'scenario_order_key' => $definition['scenario_order_key'],
                'spawn_time' => $spawnAt,
                'expires_at' => $spawnAt->addMinutes($definition['expires_after_min']),
                'pickup_deadline' => $this->deadline($start, $definition['pickup_deadline_minute'] ?? null),
                'delivery_deadline' => $this->deadline($start, $definition['delivery_deadline_minute'] ?? null),
                'restaurant_id' => $definition['restaurant']['id'],
                'restaurant_name' => $definition['restaurant']['name'],
                'restaurant_lat' => $this->coordinateString($definition['restaurant']['lat']),
                'restaurant_lon' => $this->coordinateString($definition['restaurant']['lon']),
                'customer_lat' => $this->coordinateString($definition['customer']['lat']),
                'customer_lon' => $this->coordinateString($definition['customer']['lon']),
                'destination_zone' => $definition['destination_zone'] ?? null,
                'base_pay_mxn' => $this->money($definition['base_pay_mxn']),
                'surge_bonus_mxn' => $this->money($definition['surge_bonus_mxn'] ?? 0),
                'other_bonus_mxn' => $this->money($definition['other_bonus_mxn'] ?? 0),
                'estimated_restaurant_wait_min' => $definition['estimated_restaurant_wait_min'],
                'restrictions' => $definition['restrictions'] ?? [],
                'metadata' => array_merge($sharedMetadata, $definition['metadata'] ?? [], ['is_simulated' => true]),
            ]);

            foreach ($shifts as $shift) {
                $shift->shiftOrders()->create([
                    'order_id' => $order->id,
                    'status' => OrderStatus::PENDING,
                ]);
            }
        }

        foreach ($scenario['events'] as $event) {
            $run->events()->create([
                'type' => EventType::from($event['type']),
                'scheduled_at' => $start->addMinutes($event['scheduled_minute']),
                'sequence' => $event['sequence'],
                'payload' => array_merge($event['payload'], ['is_simulated' => true]),
                'applied_at' => null,
            ]);
        }
    }

    public function advanceOffers(SimulationRun $run, DateTimeInterface $at): void
    {
        $at = CarbonImmutable::createFromInterface($at);
        ShiftOrder::query()
            ->whereHas('shift', fn ($query) => $query->where('simulation_run_id', $run->id))
            ->whereIn('status', [OrderStatus::PENDING->value, OrderStatus::AVAILABLE->value])
            ->with('order')
            ->lockForUpdate()
            ->get()
            ->each(function (ShiftOrder $shiftOrder) use ($at): void {
                $order = $shiftOrder->order;
                if ($shiftOrder->status === OrderStatus::PENDING && $order->spawn_time->lessThanOrEqualTo($at)) {
                    $shiftOrder->forceFill([
                        'status' => OrderStatus::AVAILABLE,
                        'available_at' => $order->spawn_time,
                    ])->save();
                    DB::afterCommit(fn () => SimulationUpdated::dispatch($shiftOrder->shift->simulation_run_id, 'order.available', $at->toIso8601String(), [(string) $order->external_id], ['status' => OrderStatus::AVAILABLE->value]));
                }
                if ($shiftOrder->status === OrderStatus::AVAILABLE && $order->expires_at->lessThanOrEqualTo($at)) {
                    $shiftOrder->forceFill([
                        'status' => OrderStatus::EXPIRED,
                        'expired_at' => $order->expires_at,
                    ])->save();
                    DB::afterCommit(fn () => SimulationUpdated::dispatch($shiftOrder->shift->simulation_run_id, 'order.expired', $at->toIso8601String(), [(string) $order->external_id], ['status' => OrderStatus::EXPIRED->value]));
                }
            });
    }

    private function validateOrder(mixed $order, int $index, int $duration, array &$keys, array &$externalIds): void
    {
        if (! is_array($order)) {
            throw new InvalidScenarioDefinition("orders[{$index}] must be an object.");
        }
        foreach (['scenario_order_key', 'external_id', 'spawn_minute', 'expires_after_min', 'restaurant', 'customer', 'base_pay_mxn', 'estimated_restaurant_wait_min'] as $field) {
            if (! array_key_exists($field, $order)) {
                throw new InvalidScenarioDefinition("orders[{$index}].{$field} is required.");
            }
        }
        foreach (['scenario_order_key', 'external_id'] as $field) {
            if (! is_string($order[$field]) || $order[$field] === '') {
                throw new InvalidScenarioDefinition("orders[{$index}].{$field} must be a non-empty string.");
            }
        }
        if (in_array($order['scenario_order_key'], $keys, true)) {
            throw new InvalidScenarioDefinition("orders[{$index}].scenario_order_key must be unique.");
        }
        if (in_array($order['external_id'], $externalIds, true)) {
            throw new InvalidScenarioDefinition("orders[{$index}].external_id must be unique.");
        }
        $keys[] = $order['scenario_order_key'];
        $externalIds[] = $order['external_id'];
        if (! is_int($order['spawn_minute']) || $order['spawn_minute'] < 0 || $order['spawn_minute'] > $duration) {
            throw new InvalidScenarioDefinition("orders[{$index}].spawn_minute is outside the scenario duration.");
        }
        if (! is_int($order['expires_after_min']) || $order['expires_after_min'] <= 0) {
            throw new InvalidScenarioDefinition("orders[{$index}].expires_after_min must be positive.");
        }
        foreach (['base_pay_mxn', 'surge_bonus_mxn', 'other_bonus_mxn'] as $field) {
            if (isset($order[$field]) && (! is_numeric($order[$field]) || (float) $order[$field] < 0)) {
                throw new InvalidScenarioDefinition("orders[{$index}].{$field} must be a non-negative amount.");
            }
        }
        if (! is_int($order['estimated_restaurant_wait_min']) || $order['estimated_restaurant_wait_min'] < 0) {
            throw new InvalidScenarioDefinition("orders[{$index}].estimated_restaurant_wait_min must be non-negative.");
        }
        foreach (['restaurant', 'customer'] as $place) {
            if (! is_array($order[$place])) {
                throw new InvalidScenarioDefinition("orders[{$index}].{$place} must be an object.");
            }
            try {
                Coordinates::fromArray($order[$place]);
            } catch (\Throwable $exception) {
                throw new InvalidScenarioDefinition("orders[{$index}].{$place} has invalid coordinates.", previous: $exception);
            }
        }
        foreach (['pickup_deadline_minute', 'delivery_deadline_minute'] as $field) {
            if (isset($order[$field]) && (! is_int($order[$field]) || $order[$field] < $order['spawn_minute'])) {
                throw new InvalidScenarioDefinition("orders[{$index}].{$field} must be at or after spawn_minute.");
            }
        }
    }

    /** @return array<string, mixed> */
    private function generateChallenge(int $seed): array
    {
        if ($seed < 0) {
            throw new InvalidScenarioDefinition('Challenge seed cannot be negative.');
        }
        $state = $seed === 0 ? 1 : $seed;
        $orders = [];
        $zones = ['CENTRO', 'TEC', 'SAN_PEDRO'];
        $initial = config('courier.simulation.initial_position', ['lat' => 25.675, 'lon' => -100.31]);
        for ($i = 1; $i <= 6; $i++) {
            $spawn = $this->randomInt($state, 0, 50);
            $restaurantLat = (float) $initial['lat'] + ($this->randomInt($state, -35, 35) / 10000);
            $restaurantLon = (float) $initial['lon'] + ($this->randomInt($state, -35, 35) / 10000);
            $customerLat = $restaurantLat + ($this->randomInt($state, -25, 25) / 10000);
            $customerLon = $restaurantLon + ($this->randomInt($state, -25, 25) / 10000);
            $orders[] = [
                'scenario_order_key' => sprintf('challenge-%d-%02d', $seed, $i),
                'external_id' => sprintf('CH-%d-%02d', $seed, $i),
                'spawn_minute' => $spawn,
                'expires_after_min' => $this->randomInt($state, 18, 38),
                'restaurant' => ['id' => sprintf('CH-REST-%02d', $i), 'name' => 'Challenge Kitchen '.$i, 'lat' => $restaurantLat, 'lon' => $restaurantLon],
                'customer' => ['lat' => $customerLat, 'lon' => $customerLon],
                'destination_zone' => $zones[$this->randomInt($state, 0, count($zones) - 1)],
                'base_pay_mxn' => number_format((float) $this->randomInt($state, 70, 145), 2, '.', ''),
                'surge_bonus_mxn' => number_format((float) $this->randomInt($state, 0, 25), 2, '.', ''),
                'other_bonus_mxn' => '0.00',
                'estimated_restaurant_wait_min' => $this->randomInt($state, 2, 9),
                'pickup_deadline_minute' => $spawn + 20,
                'delivery_deadline_minute' => $spawn + 50,
                'restrictions' => [],
                'metadata' => ['is_simulated' => true],
            ];
        }

        return [
            'scenario_key' => 'challenge',
            'version' => 1,
            'timezone' => (string) config('courier.simulation.timezone', 'America/Monterrey'),
            'duration_min' => (int) config('courier.simulation.duration_min', 120),
            'initial_position' => $initial,
            'metadata' => ['is_simulated' => true, 'data_origin' => 'seeded_challenge', 'payments' => 'simulated', 'demand' => 'simulated', 'surge' => 'simulated', 'traffic' => 'simulated'],
            'orders' => $orders,
            'events' => [['type' => 'TRAFFIC_CHANGED', 'scheduled_minute' => 60, 'sequence' => 1, 'payload' => ['traffic_factor' => 1.2, 'is_simulated' => true]]],
        ];
    }

    private function randomInt(int &$state, int $min, int $max): int
    {
        $state = (int) (($state * 1664525 + 1013904223) & 0x7FFFFFFF);

        return $min + ($state % ($max - $min + 1));
    }

    private function deadline(CarbonImmutable $start, ?int $minute): ?CarbonImmutable
    {
        return $minute === null ? null : $start->addMinutes($minute);
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function coordinateString(mixed $value): string
    {
        return number_format((float) $value, 7, '.', '');
    }
}
