<?php

declare(strict_types=1);

namespace App\Support;

use App\DTOs\Coordinates;
use App\Models\Shift;

final class MapPresenter
{
    /** @return array<string, mixed> */
    public function present(Shift $shift, ?array $recommendation = null): array
    {
        $shift->loadMissing('shiftOrders.order');
        $features = [];
        $features[] = $this->point('courier-'.$shift->id, 'courier', 'COURIER_AI', new Coordinates((float) $shift->current_lat, (float) $shift->current_lon));

        foreach ($shift->shiftOrders as $shiftOrder) {
            $order = $shiftOrder->order;
            if ($order === null) {
                continue;
            }
            $features[] = $this->point('pickup-'.$order->external_id, 'pickup', (string) $order->external_id, new Coordinates((float) $order->restaurant_lat, (float) $order->restaurant_lon), ['status' => $shiftOrder->status->value]);
            $features[] = $this->point('dropoff-'.$order->external_id, 'dropoff', (string) $order->external_id, new Coordinates((float) $order->customer_lat, (float) $order->customer_lon), ['status' => $shiftOrder->status->value]);
        }
        $environment = (array) ($shift->simulationRun?->environment_state ?? []);
        foreach (array_values((array) ($environment['road_closures'] ?? [])) as $index => $closure) {
            $geometry = $closure['geometry'] ?? null;
            if (is_array($geometry) && ($geometry['type'] ?? null) === 'LineString' && count((array) ($geometry['coordinates'] ?? [])) >= 2) {
                $features[] = ['type' => 'Feature', 'id' => 'closure-'.($closure['closure_id'] ?? $index), 'geometry' => $geometry, 'properties' => ['kind' => 'road-closure', 'is_simulated' => true]];
            }
        }

        $plan = $recommendation['plan'] ?? [];
        $sequence = array_values((array) ($plan['sequence'] ?? []));
        $orders = $shift->shiftOrders->mapWithKeys(fn ($item) => [(string) ($item->order?->external_id ?? '') => $item->order])->all();
        $routePoints = [new Coordinates((float) $shift->current_lat, (float) $shift->current_lon)];
        foreach ($sequence as $stop) {
            if (! is_array($stop) || ! isset($stop['action'], $stop['order_id'])) {
                continue;
            }
            $order = $orders[(string) $stop['order_id']] ?? null;
            if ($order === null) {
                continue;
            }
            $routePoints[] = $stop['action'] === 'PICKUP'
                ? new Coordinates((float) $order->restaurant_lat, (float) $order->restaurant_lon)
                : new Coordinates((float) $order->customer_lat, (float) $order->customer_lon);
        }

        $geometry = count($routePoints) >= 2 ? Geo::lineString($routePoints) : null;
        if ($geometry !== null) {
            $features[] = [
                'type' => 'Feature',
                'id' => 'recommended-route-'.$shift->id,
                'geometry' => $geometry,
                'properties' => [
                    'kind' => 'recommended-route',
                    'provider' => 'fallback',
                    'is_fallback' => true,
                    'warning' => 'Geometría estimada; el proveedor OSRM no entregó geometría persistida para este plan.',
                ],
            ];
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
            'route' => [
                'provider' => 'fallback',
                'is_fallback' => true,
                'has_geometry' => $geometry !== null,
            ],
            'legend' => [
                'fallback' => 'Estimación local',
                'osrm' => 'OSRM',
            ],
        ];
    }

    /** @param array<string, mixed> $properties */
    private function point(string $id, string $kind, string $orderId, Coordinates $coordinates, array $properties = []): array
    {
        return [
            'type' => 'Feature',
            'id' => $id,
            'geometry' => ['type' => 'Point', 'coordinates' => [$coordinates->lon, $coordinates->lat]],
            'properties' => ['kind' => $kind, 'order_id' => $orderId] + $properties,
        ];
    }
}
