<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EventType;
use App\Events\SimulationUpdated;
use App\Exceptions\InvalidSimulationEvent;
use App\Models\SimulationEvent;
use App\Models\SimulationRun;
use App\Support\Money;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

final class SimulationEventService
{
    public function __construct(private readonly RecommendationService $recommendationService) {}

    public function schedule(SimulationRun $run, EventType|string $type, DateTimeInterface $scheduledAt, array $payload, int $sequence = 0): SimulationEvent
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new InvalidSimulationEvent('Dynamic event injection is enabled only for local/demo environments.');
        }
        $eventType = $type instanceof EventType ? $type : EventType::tryFrom($type);
        if ($eventType === null || $sequence < 0) {
            throw new InvalidSimulationEvent('Event type or sequence is invalid.');
        }
        if (($payload['is_simulated'] ?? true) !== true) {
            throw new InvalidSimulationEvent('Dynamic event payload must be labelled simulated.');
        }
        $when = CarbonImmutable::createFromInterface($scheduledAt);
        if ($when->lessThan($run->simulated_started_at) || $when->greaterThan($run->simulated_ends_at)) {
            throw new InvalidSimulationEvent('Event time must be within the simulation window.');
        }

        return $run->events()->create([
            'type' => $eventType,
            'scheduled_at' => $when,
            'sequence' => $sequence,
            'payload' => $payload + ['is_simulated' => true],
        ]);
    }

    public function applyDue(SimulationRun $run, DateTimeInterface $at): int
    {
        return DB::transaction(function () use ($run, $at): int {
            $lockedRun = SimulationRun::query()->lockForUpdate()->findOrFail($run->id);
            $applied = 0;
            $events = $lockedRun->events()->pendingAt($at)->lockForUpdate()->get();
            foreach ($events as $event) {
                $before = $this->snapshot($lockedRun);
                $reasons = $this->applyEvent($lockedRun, $event);
                $lockedRun->refresh();
                $comparison = $this->reoptimize($lockedRun);
                $lockedRun->refresh();
                $event->forceFill([
                    'applied_at' => $at,
                    'state_before' => $before,
                    'state_after' => $this->snapshot($lockedRun) + ['reoptimization' => $comparison],
                    'reason_codes' => $reasons,
                ])->save();
                $broadcastType = match ($event->type) {
                    EventType::SURGE_STARTED, EventType::SURGE_ENDED => 'surge.changed',
                    EventType::TRAFFIC_CHANGED => 'traffic.changed',
                    EventType::WEATHER_CHANGED => 'weather.changed',
                    EventType::ROAD_CLOSED, EventType::ROAD_REOPENED => 'closure.changed',
                    EventType::RESTAURANT_DELAY_CHANGED => 'restaurant.delay.changed',
                    default => 'event.applied',
                };
                DB::afterCommit(fn () => SimulationUpdated::dispatch($lockedRun->id, $broadcastType, CarbonImmutable::createFromInterface($at)->toIso8601String(), [(string) $event->id], ['event_type' => $event->type->value]));
                $applied++;
            }

            return $applied;
        });
    }

    /** @return list<string> */
    private function applyEvent(SimulationRun $run, SimulationEvent $event): array
    {
        $payload = $event->payload ?? [];
        $type = $event->type;
        $reasons = ['DYNAMIC_EVENT', 'SIMULATED_DATA'];

        match ($type) {
            EventType::TRAFFIC_CHANGED => $this->applyTraffic($run, $payload),
            EventType::WEATHER_CHANGED => $this->applyWeather($run, $payload),
            EventType::SURGE_STARTED => $this->applySurge($run, $payload, true),
            EventType::SURGE_ENDED => $this->applySurge($run, $payload, false),
            EventType::ROAD_CLOSED => $this->applyClosure($run, $payload, true),
            EventType::ROAD_REOPENED => $this->applyClosure($run, $payload, false),
            EventType::RESTAURANT_DELAY_CHANGED => $this->applyRestaurantDelay($run, $payload),
            default => null,
        };

        $run->refresh();
        $run->forceFill(['environment_state' => (array) ($run->environment_state ?? []) + ['is_simulated' => true]])->save();

        return $reasons;
    }

    private function applyTraffic(SimulationRun $run, array $payload): void
    {
        $factor = $payload['traffic_factor'] ?? null;
        if (! is_numeric($factor) || (float) $factor <= 0) {
            throw new InvalidSimulationEvent('traffic_factor must be greater than zero.');
        }
        $run->forceFill(['traffic_factor' => number_format((float) $factor, 4, '.', '')])->save();
    }

    private function applyWeather(SimulationRun $run, array $payload): void
    {
        $weather = trim((string) ($payload['weather'] ?? ''));
        if ($weather === '') {
            throw new InvalidSimulationEvent('weather is required.');
        }
        $run->forceFill(['weather' => $weather])->save();
    }

    private function applySurge(SimulationRun $run, array $payload, bool $started): void
    {
        $zone = trim((string) ($payload['zone'] ?? ''));
        $multiplier = (float) ($payload['multiplier'] ?? 1);
        if ($zone === '' || $multiplier <= 0) {
            throw new InvalidSimulationEvent('Surge requires zone and positive multiplier.');
        }
        $orders = $run->orders()->where('destination_zone', $zone)->lockForUpdate()->get();
        foreach ($orders as $order) {
            $current = (string) $order->surge_bonus_mxn;
            $new = $started ? Money::multiply($current, $multiplier) : Money::multiply($current, 1 / $multiplier);
            $order->forceFill(['surge_bonus_mxn' => $new, 'metadata' => (array) ($order->metadata ?? []) + ['is_simulated' => true, 'dynamic_surge_zone' => $zone]])->save();
        }
        $environment = (array) ($run->environment_state ?? []);
        $zones = collect((array) ($environment['surge_zones'] ?? []))->reject(fn ($item) => ($item['zone'] ?? null) === $zone)->values()->all();
        if ($started) {
            $zones[] = ['zone' => $zone, 'multiplier' => $multiplier, 'is_simulated' => true];
        }
        $run->forceFill(['environment_state' => array_replace($environment, ['surge_zones' => $zones])])->save();
    }

    private function applyClosure(SimulationRun $run, array $payload, bool $closed): void
    {
        $closureId = trim((string) ($payload['closure_id'] ?? $payload['id'] ?? ''));
        if ($closureId === '') {
            throw new InvalidSimulationEvent('closure_id is required.');
        }
        $environment = (array) ($run->environment_state ?? []);
        $closures = collect((array) ($environment['road_closures'] ?? []))->reject(fn ($item) => ($item['closure_id'] ?? null) === $closureId)->values()->all();
        if ($closed) {
            $closures[] = array_replace($payload, ['closure_id' => $closureId, 'geometry' => $payload['geometry'] ?? null, 'closed' => true, 'is_simulated' => true]);
        }
        $run->forceFill(['environment_state' => array_replace($environment, ['road_closures' => $closures]), 'closure_version' => (int) $run->closure_version + 1])->save();
    }

    private function applyRestaurantDelay(SimulationRun $run, array $payload): void
    {
        $restaurantId = trim((string) ($payload['restaurant_id'] ?? ''));
        $wait = $payload['wait_min'] ?? $payload['estimated_restaurant_wait_min'] ?? null;
        if ($restaurantId === '' || ! is_numeric($wait) || (int) $wait < 0) {
            throw new InvalidSimulationEvent('Restaurant delay requires restaurant_id and non-negative wait_min.');
        }
        $run->orders()->where('restaurant_id', $restaurantId)->whereHas('shiftOrders', fn ($query) => $query->where('status', '!=', 'DELIVERED'))->lockForUpdate()->get()->each(fn ($order) => $order->forceFill(['estimated_restaurant_wait_min' => (int) $wait])->save());
    }

    /** @return array<string, mixed> */
    private function snapshot(SimulationRun $run): array
    {
        return ['traffic_factor' => (float) $run->traffic_factor, 'weather' => $run->weather, 'closure_version' => (int) $run->closure_version, 'environment_state' => $run->environment_state, 'is_simulated' => true];
    }

    /** @return array<string, mixed> */
    private function reoptimize(SimulationRun $run): array
    {
        $changes = [];
        foreach ($run->shifts()->lockForUpdate()->get() as $shift) {
            $previous = $shift->recommendations()->latest('id')->first();
            $next = $this->recommendationService->recommend($shift, $run)['recommendation'];
            $before = $previous?->selected_order_ids ?? [];
            $after = $next->selected_order_ids ?? [];
            $changes[] = ['shift_id' => $shift->id, 'before_order_ids' => $before, 'after_order_ids' => $after, 'ranking_before' => $previous?->ranking ?? [], 'ranking_after' => $next->ranking ?? [], 'changed' => $before !== $after];
            DB::afterCommit(fn () => SimulationUpdated::dispatch($run->id, 'ranking.updated', $run->simulated_current_at->toIso8601String(), [(string) $shift->id, (string) $next->id], ['selected_order_ids' => $after]));
        }

        return $changes;
    }
}
