<?php

declare(strict_types=1);

namespace App\Support;

use App\DTOs\Coordinates;
use App\DTOs\CourierState;
use App\DTOs\EnvironmentState;
use App\Enums\OrderStatus;
use App\Models\Shift;
use App\Models\SimulationRun;
use DateTimeImmutable;

final class StateMapper
{
    public function toCourierState(Shift $shift, ?SimulationRun $run = null): CourierState
    {
        $run ??= $shift->simulationRun;

        $currentOrders = $shift->shiftOrders()
            ->with('order')
            ->whereIn('status', [
                OrderStatus::ACCEPTED->value,
                OrderStatus::PICKING_UP->value,
                OrderStatus::PICKED_UP->value,
                OrderStatus::DELIVERING->value,
            ])
            ->get()
            ->map(static fn ($shiftOrder): string => (string) ($shiftOrder->order?->external_id ?? $shiftOrder->order_id))
            ->values()
            ->all();

        $remainingSeconds = max(
            0,
            $run->simulated_ends_at->getTimestamp() - $run->simulated_current_at->getTimestamp()
        );

        return new CourierState(
            courierId: 'SHIFT-'.$shift->id,
            position: new Coordinates((float) $shift->current_lat, (float) $shift->current_lon),
            vehicle: (string) config('courier.simulation.vehicle', 'motorcycle'),
            shiftRemainingMin: intdiv($remainingSeconds, 60),
            currentOrders: $currentOrders,
            maxConcurrentOrders: (int) $shift->max_concurrent_orders,
            costPerKmMxn: (string) $shift->cost_per_km_mxn,
        );
    }

    public function toEnvironmentState(SimulationRun $run): EnvironmentState
    {
        $environment = $run->environment_state ?? [];

        return new EnvironmentState(
            simulationTime: DateTimeImmutable::createFromInterface($run->simulated_current_at),
            weather: (string) ($run->weather ?: 'clear'),
            trafficFactor: (float) $run->traffic_factor,
            surgeZones: array_values((array) ($environment['surge_zones'] ?? [])),
            roadClosures: array_values((array) ($environment['road_closures'] ?? [])),
            closureVersion: (int) $run->closure_version,
        );
    }
}
