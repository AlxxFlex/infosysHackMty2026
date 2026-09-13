<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DemandZone;
use App\Models\Order;
use App\Support\Money;
use DateTimeInterface;
use Illuminate\Support\Carbon;

final class DemandService
{
    public function score(Order|string|null $orderOrZone, DateTimeInterface $at): float
    {
        $zoneKey = $orderOrZone instanceof Order
            ? $orderOrZone->destination_zone
            : $orderOrZone;

        if ($zoneKey === null || $zoneKey === '') {
            return 0.5;
        }

        $zone = DemandZone::query()->where('zone_key', $zoneKey)->first();
        if ($zone === null || ! $zone->is_active) {
            return 0.0;
        }

        // Simulated timestamps are scenario wall-clock values; do not shift the hour
        // when the database connection serializes them in the application timezone.
        $time = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $at->format('Y-m-d H:i:s'),
            (string) config('courier.simulation.timezone', 'America/Monterrey'),
        );
        $demand = $zone->demand_by_hour[(string) $time->hour] ?? null;

        if (! is_numeric($demand)) {
            return 0.5;
        }

        return max(0.0, min(1.0, (float) $demand));
    }

    public function futurePositionBonus(Order|string|null $orderOrZone, DateTimeInterface $at): string
    {
        return Money::multiply(
            (string) $this->score($orderOrZone, $at),
            (float) config('courier.demand.max_position_bonus_mxn', 20),
        );
    }
}
