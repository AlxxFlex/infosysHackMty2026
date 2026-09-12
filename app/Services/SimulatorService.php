<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ShiftStatus;
use App\Exceptions\InvalidSimulationTick;
use App\Models\Shift;
use App\Models\SimulationRun;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

final class SimulatorService
{
    public function tick(
        SimulationRun|int $run,
        int $minutes,
        ?string $idempotencyKey = null,
    ): SimulationRun {
        if ($minutes <= 0) {
            throw new InvalidSimulationTick('Tick minutes must be greater than zero.');
        }

        $idempotencyKey = $this->normalizeIdempotencyKey($idempotencyKey);

        return DB::transaction(function () use ($run, $minutes, $idempotencyKey): SimulationRun {
            $lockedRun = $this->lockRun($run);

            if ($idempotencyKey !== null && DB::table('simulation_tick_requests')
                ->where('simulation_run_id', $lockedRun->id)
                ->where('idempotency_key', $idempotencyKey)
                ->exists()) {
                return $lockedRun->fresh('shifts');
            }

            if ($lockedRun->status !== ShiftStatus::RUNNING) {
                return $lockedRun->fresh('shifts');
            }

            $from = CarbonImmutable::instance($lockedRun->simulated_current_at);
            $end = CarbonImmutable::instance($lockedRun->simulated_ends_at);
            $target = $from->addMinutes($minutes);

            if ($target->greaterThan($end)) {
                $target = $end;
            }

            $advancedMinutes = max(0, (int) $from->diffInMinutes($target));
            $this->advanceShifts($lockedRun, $advancedMinutes);

            $lockedRun->forceFill([
                'simulated_current_at' => $target,
                'real_last_tick_at' => now(),
            ])->save();

            if ($target->greaterThanOrEqualTo($end)) {
                $lockedRun->forceFill([
                    'status' => ShiftStatus::FINISHED,
                    'real_finished_at' => now(),
                ])->save();
                $this->setShiftStatus($lockedRun, ShiftStatus::FINISHED);
            }

            if ($idempotencyKey !== null) {
                DB::table('simulation_tick_requests')->insert([
                    'simulation_run_id' => $lockedRun->id,
                    'idempotency_key' => $idempotencyKey,
                    'requested_minutes' => $minutes,
                    'simulated_from' => $from,
                    'simulated_to' => $target,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $lockedRun->fresh('shifts');
        });
    }

    public function syncElapsedRealTime(
        SimulationRun|int $run,
        ?DateTimeInterface $realNow = null,
    ): SimulationRun {
        $observedAt = $realNow === null
            ? CarbonImmutable::now()
            : CarbonImmutable::createFromInterface($realNow);

        return DB::transaction(function () use ($run, $observedAt): SimulationRun {
            $lockedRun = $this->lockRun($run);

            if ($lockedRun->status !== ShiftStatus::RUNNING) {
                return $lockedRun->fresh('shifts');
            }

            $anchor = $lockedRun->real_last_tick_at;

            if ($anchor === null) {
                $lockedRun->forceFill(['real_last_tick_at' => $observedAt])->save();

                return $lockedRun->fresh('shifts');
            }

            $elapsedSeconds = max(0, $observedAt->getTimestamp() - $anchor->getTimestamp());
            $speedMultiplier = (float) $lockedRun->speed_multiplier;

            if ($speedMultiplier <= 0) {
                throw new InvalidSimulationTick('Speed multiplier must be greater than zero.');
            }

            $simulatedMinutes = (int) floor(($elapsedSeconds * $speedMultiplier) / 60);

            if ($simulatedMinutes <= 0) {
                return $lockedRun->fresh('shifts');
            }

            $from = CarbonImmutable::instance($lockedRun->simulated_current_at);
            $end = CarbonImmutable::instance($lockedRun->simulated_ends_at);
            $target = $from->addMinutes($simulatedMinutes);

            if ($target->greaterThan($end)) {
                $target = $end;
            }

            $advancedMinutes = max(0, (int) $from->diffInMinutes($target));
            $this->advanceShifts($lockedRun, $advancedMinutes);

            $consumedSeconds = max(1, (int) floor(($advancedMinutes * 60) / $speedMultiplier));
            $newAnchor = $anchor->copy()->addSeconds($consumedSeconds);

            if ($newAnchor->greaterThan($observedAt)) {
                $newAnchor = $observedAt;
            }

            $lockedRun->forceFill([
                'simulated_current_at' => $target,
                'real_last_tick_at' => $newAnchor,
            ])->save();

            if ($target->greaterThanOrEqualTo($end)) {
                $lockedRun->forceFill([
                    'status' => ShiftStatus::FINISHED,
                    'real_finished_at' => $observedAt,
                ])->save();
                $this->setShiftStatus($lockedRun, ShiftStatus::FINISHED);
            }

            return $lockedRun->fresh('shifts');
        });
    }

    private function normalizeIdempotencyKey(?string $key): ?string
    {
        if ($key === null) {
            return null;
        }

        $key = trim($key);

        if ($key === '' || strlen($key) > 128) {
            throw new InvalidSimulationTick('Idempotency key must contain 1 to 128 characters.');
        }

        return $key;
    }

    private function lockRun(SimulationRun|int $run): SimulationRun
    {
        $runId = $run instanceof SimulationRun ? $run->getKey() : $run;

        if ($runId === null) {
            throw new InvalidSimulationTick('Simulation run must be persisted.');
        }

        return SimulationRun::query()->lockForUpdate()->findOrFail($runId);
    }

    private function advanceShifts(SimulationRun $run, int $minutes): void
    {
        if ($minutes <= 0) {
            return;
        }

        $run->shifts()->lockForUpdate()->get()->each(
            function (Shift $shift) use ($minutes): void {
                if ($shift->status !== ShiftStatus::RUNNING) {
                    return;
                }

                $hasActiveOrders = $shift->shiftOrders()
                    ->whereIn('status', [
                        OrderStatus::ACCEPTED->value,
                        OrderStatus::PICKING_UP->value,
                        OrderStatus::PICKED_UP->value,
                        OrderStatus::DELIVERING->value,
                    ])
                    ->exists();
                $column = $hasActiveOrders ? 'active_minutes' : 'idle_minutes';

                $shift->forceFill([
                    $column => (int) $shift->{$column} + $minutes,
                ])->save();
            }
        );
    }

    private function setShiftStatus(SimulationRun $run, ShiftStatus $status): void
    {
        $run->shifts()->lockForUpdate()->get()->each(
            function (Shift $shift) use ($status): void {
                $shift->forceFill(['status' => $status])->save();
            }
        );
    }
}
