<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Coordinates;
use App\DTOs\CourierState;
use App\DTOs\EnvironmentState;
use App\Enums\AgentType;
use App\Enums\CourierStatus;
use App\Enums\ShiftStatus;
use App\Exceptions\InvalidSimulationTransition;
use App\Models\Shift;
use App\Models\SimulationRun;
use App\Support\StateMapper;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class ShiftService
{
    public function __construct(private readonly StateMapper $stateMapper) {}

    public function createRun(
        string $scenarioKey,
        int $seed,
        ?DateTimeInterface $simulatedStartedAt = null,
        ?array $configSnapshot = null,
    ): SimulationRun {
        if ($scenarioKey === '') {
            throw new InvalidSimulationTransition('Scenario key cannot be empty.');
        }

        if ($seed < 0) {
            throw new InvalidSimulationTransition('Seed cannot be negative.');
        }

        $configSnapshot ??= config('courier');
        $start = $simulatedStartedAt === null
            ? CarbonImmutable::parse((string) config('courier.simulation.default_start_at'))
            : CarbonImmutable::createFromInterface($simulatedStartedAt);
        $duration = (int) config('courier.simulation.duration_min', 120);
        $speedMultiplier = (float) config('courier.simulation.speed_multiplier', 1);
        $maxConcurrentOrders = (int) config('courier.simulation.max_concurrent_orders', 2);
        $costPerKmMxn = (float) config('courier.simulation.cost_per_km_mxn', 1.25);

        if ($duration <= 0 || $speedMultiplier <= 0 || $maxConcurrentOrders <= 0 || $costPerKmMxn < 0) {
            throw new InvalidSimulationTransition('Simulation configuration contains invalid limits.');
        }

        $position = config('courier.simulation.initial_position', [
            'lat' => 25.675,
            'lon' => -100.310,
        ]);
        $coordinates = Coordinates::fromArray($position);

        return DB::transaction(function () use ($scenarioKey, $seed, $start, $duration, $speedMultiplier, $maxConcurrentOrders, $costPerKmMxn, $coordinates, $configSnapshot): SimulationRun {
            $run = SimulationRun::query()->create([
                'scenario_key' => $scenarioKey,
                'seed' => $seed,
                'status' => ShiftStatus::IDLE,
                'simulated_started_at' => $start,
                'simulated_current_at' => $start,
                'simulated_ends_at' => $start->addMinutes($duration),
                'traffic_factor' => '1.0000',
                'weather' => 'clear',
                'closure_version' => 0,
                'speed_multiplier' => (string) $speedMultiplier,
                'environment_state' => [
                    'surge_zones' => [],
                    'road_closures' => [],
                    'is_simulated' => true,
                ],
                'config_snapshot' => $configSnapshot,
            ]);

            foreach (AgentType::cases() as $agentType) {
                $run->shifts()->create([
                    'agent_type' => $agentType,
                    'status' => ShiftStatus::IDLE,
                    'courier_status' => CourierStatus::AVAILABLE,
                    'initial_lat' => (string) $coordinates->lat,
                    'initial_lon' => (string) $coordinates->lon,
                    'current_lat' => (string) $coordinates->lat,
                    'current_lon' => (string) $coordinates->lon,
                    'max_concurrent_orders' => $maxConcurrentOrders,
                    'cost_per_km_mxn' => number_format($costPerKmMxn, 2, '.', ''),
                ]);
            }

            return $run->load('shifts');
        });
    }

    public function startRun(SimulationRun|int $run): SimulationRun
    {
        return $this->transition($run, [ShiftStatus::IDLE], ShiftStatus::RUNNING, true);
    }

    public function pauseRun(SimulationRun|int $run): SimulationRun
    {
        return $this->transition($run, [ShiftStatus::RUNNING], ShiftStatus::PAUSED, false);
    }

    public function resumeRun(SimulationRun|int $run): SimulationRun
    {
        return $this->transition($run, [ShiftStatus::PAUSED], ShiftStatus::RUNNING, true);
    }

    public function finishRun(SimulationRun|int $run): SimulationRun
    {
        return DB::transaction(function () use ($run): SimulationRun {
            $lockedRun = $this->lockRun($run);

            if (! in_array($lockedRun->status, [ShiftStatus::RUNNING, ShiftStatus::PAUSED], true)) {
                throw new InvalidSimulationTransition(
                    "Cannot finish a run in {$lockedRun->status->value} state."
                );
            }

            $lockedRun->forceFill([
                'status' => ShiftStatus::FINISHED,
                'real_finished_at' => now(),
            ])->save();
            $this->setShiftStatus($lockedRun, ShiftStatus::FINISHED);

            return $lockedRun->fresh('shifts');
        });
    }

    /**
     * @return array{run: SimulationRun, shifts: Collection<int, Shift>, courier_states: array<int, CourierState>, environment_state: EnvironmentState}
     */
    public function getRunState(SimulationRun|int $run): array
    {
        $resolvedRun = $this->resolveRun($run)->load('shifts');

        return [
            'run' => $resolvedRun,
            'shifts' => $resolvedRun->shifts,
            'courier_states' => $resolvedRun->shifts
                ->mapWithKeys(fn (Shift $shift): array => [
                    $shift->id => $this->stateMapper->toCourierState($shift, $resolvedRun),
                ])
                ->all(),
            'environment_state' => $this->stateMapper->toEnvironmentState($resolvedRun),
        ];
    }

    public function resolveRun(SimulationRun|int $run): SimulationRun
    {
        if ($run instanceof SimulationRun) {
            if (! $run->exists || ! $run->getKey()) {
                throw new InvalidSimulationTransition('Simulation run must be persisted.');
            }

            return $run;
        }

        return SimulationRun::query()->findOrFail($run);
    }

    private function transition(
        SimulationRun|int $run,
        array $allowedStatuses,
        ShiftStatus $targetStatus,
        bool $resetRealAnchor,
    ): SimulationRun {
        return DB::transaction(function () use ($run, $allowedStatuses, $targetStatus, $resetRealAnchor): SimulationRun {
            $lockedRun = $this->lockRun($run);

            if (! in_array($lockedRun->status, $allowedStatuses, true)) {
                throw new InvalidSimulationTransition(
                    "Cannot transition run from {$lockedRun->status->value} to {$targetStatus->value}."
                );
            }

            $attributes = ['status' => $targetStatus];

            if ($resetRealAnchor) {
                if ($lockedRun->status === ShiftStatus::IDLE) {
                    $attributes['real_started_at'] = now();
                }
                $attributes['real_last_tick_at'] = now();
            } else {
                $attributes['real_last_tick_at'] = now();
            }

            $lockedRun->forceFill($attributes)->save();
            $this->setShiftStatus($lockedRun, $targetStatus);

            return $lockedRun->fresh('shifts');
        });
    }

    private function lockRun(SimulationRun|int $run): SimulationRun
    {
        $runId = $run instanceof SimulationRun ? $run->getKey() : $run;

        return SimulationRun::query()->lockForUpdate()->findOrFail($runId);
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
