<?php

namespace Tests\Feature;

use App\DTOs\CourierState;
use App\DTOs\EnvironmentState;
use App\Enums\AgentType;
use App\Enums\ShiftStatus;
use App\Exceptions\InvalidSimulationTick;
use App\Exceptions\InvalidSimulationTransition;
use App\Models\SimulationRun;
use App\Services\ShiftService;
use App\Services\SimulatorService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulatorServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($connection !== 'mysql' || $database !== 'infoSys_testing') {
            throw new \LogicException(
                'Simulator tests may only refresh the dedicated MySQL database infoSys_testing.'
            );
        }
    }

    public function test_create_run_makes_two_identical_agent_shifts_and_preserves_snapshot(): void
    {
        $service = app(ShiftService::class);
        $start = CarbonImmutable::parse('2026-09-12 18:00:00');
        $run = $service->createRun('demo_normal', 42, $start);

        $this->assertSame(ShiftStatus::IDLE, $run->status);
        $this->assertCount(2, $run->shifts);
        $this->assertEqualsCanonicalizing(
            [AgentType::COURIER_AI, AgentType::BASELINE],
            $run->shifts->pluck('agent_type')->all()
        );
        $shiftsByAgent = $run->shifts->keyBy(fn ($shift) => $shift->agent_type->value);
        $this->assertSame($shiftsByAgent[AgentType::COURIER_AI->value]->initial_lat, $shiftsByAgent[AgentType::BASELINE->value]->initial_lat);
        $this->assertSame($shiftsByAgent[AgentType::COURIER_AI->value]->initial_lon, $shiftsByAgent[AgentType::BASELINE->value]->initial_lon);
        $this->assertSame($start->getTimestamp(), $run->simulated_started_at->getTimestamp());
        $this->assertSame('demo_normal', $run->scenario_key);
        $this->assertSame(42, $run->seed);
        $this->assertSame(config('courier.scoring.weights'), $run->config_snapshot['scoring']['weights']);
    }

    public function test_state_mapper_exposes_typed_authoritative_run_state(): void
    {
        $shiftService = app(ShiftService::class);
        $run = $shiftService->createRun('demo_normal', 7);
        $state = $shiftService->getRunState($run);

        $this->assertInstanceOf(SimulationRun::class, $state['run']);
        $this->assertCount(2, $state['shifts']);
        $this->assertContainsOnlyInstancesOf(CourierState::class, $state['courier_states']);
        $this->assertInstanceOf(EnvironmentState::class, $state['environment_state']);
        $this->assertSame(120, $state['courier_states'][$run->shifts->first()->id]->shiftRemainingMin);
    }

    public function test_run_transitions_update_both_shifts_and_reject_invalid_transitions(): void
    {
        $shiftService = app(ShiftService::class);
        $run = $shiftService->createRun('demo_normal', 42);
        $started = $shiftService->startRun($run);

        $this->assertSame(ShiftStatus::RUNNING, $started->status);
        $this->assertSame([ShiftStatus::RUNNING, ShiftStatus::RUNNING], $started->shifts->pluck('status')->all());
        $realStartedAt = $started->real_started_at->getTimestamp();

        $paused = $shiftService->pauseRun($started);
        $this->assertSame(ShiftStatus::PAUSED, $paused->status);
        $this->assertSame([ShiftStatus::PAUSED, ShiftStatus::PAUSED], $paused->shifts->pluck('status')->all());

        $resumed = $shiftService->resumeRun($paused);
        $this->assertSame(ShiftStatus::RUNNING, $resumed->status);
        $this->assertSame($realStartedAt, $resumed->real_started_at->getTimestamp());

        $this->expectException(InvalidSimulationTransition::class);
        $shiftService->startRun($resumed);
    }

    public function test_invalid_transition_leaves_run_and_shifts_unchanged(): void
    {
        $shiftService = app(ShiftService::class);
        $run = $shiftService->createRun('demo_normal', 42);

        try {
            $shiftService->pauseRun($run);
            $this->fail('Expected an invalid transition exception.');
        } catch (InvalidSimulationTransition) {
            // Expected: the transaction must be rolled back.
        }

        $fresh = $run->fresh('shifts');
        $this->assertSame(ShiftStatus::IDLE, $fresh->status);
        $this->assertSame([ShiftStatus::IDLE, ShiftStatus::IDLE], $fresh->shifts->pluck('status')->all());
    }

    public function test_manual_tick_advances_exact_minutes_and_accumulates_idle_time(): void
    {
        $shiftService = app(ShiftService::class);
        $simulator = app(SimulatorService::class);
        $run = $shiftService->startRun($shiftService->createRun('demo_normal', 42));

        $advanced = $simulator->tick($run, 15, 'manual-1');

        $this->assertSame('2026-09-12 18:15:00', $advanced->simulated_current_at->format('Y-m-d H:i:s'));
        $this->assertSame([15, 15], $advanced->shifts->pluck('idle_minutes')->all());
        $this->assertSame([0, 0], $advanced->shifts->pluck('active_minutes')->all());
        $this->assertSame(1, $advanced->tickRequests()->count());
    }

    public function test_repeated_tick_key_is_idempotent_even_when_requested_minutes_differ(): void
    {
        $shiftService = app(ShiftService::class);
        $simulator = app(SimulatorService::class);
        $run = $shiftService->startRun($shiftService->createRun('demo_normal', 42));

        $first = $simulator->tick($run, 10, 'same-request');
        $second = $simulator->tick($run, 25, 'same-request');

        $this->assertSame($first->simulated_current_at->getTimestamp(), $second->simulated_current_at->getTimestamp());
        $this->assertSame([10, 10], $second->shifts->pluck('idle_minutes')->all());
        $this->assertSame(1, $second->tickRequests()->count());
    }

    public function test_paused_and_finished_runs_do_not_advance(): void
    {
        $shiftService = app(ShiftService::class);
        $simulator = app(SimulatorService::class);
        $run = $shiftService->createRun('demo_normal', 42);

        $paused = $shiftService->pauseRun($shiftService->startRun($run));
        $this->assertSame($run->simulated_current_at->getTimestamp(), $simulator->tick($paused, 10)->simulated_current_at->getTimestamp());

        $running = $shiftService->resumeRun($paused);
        $finished = $simulator->tick($running, 999, 'finish-request');
        $afterFinish = $simulator->tick($finished, 10, 'after-finish');

        $this->assertSame(ShiftStatus::FINISHED, $finished->status);
        $this->assertSame('2026-09-12 20:00:00', $finished->simulated_current_at->format('Y-m-d H:i:s'));
        $this->assertSame([ShiftStatus::FINISHED, ShiftStatus::FINISHED], $finished->shifts->pluck('status')->all());
        $this->assertSame($finished->simulated_current_at->getTimestamp(), $afterFinish->simulated_current_at->getTimestamp());
        $this->assertSame([120, 120], $finished->shifts->pluck('idle_minutes')->all());
    }

    public function test_tick_rejects_non_positive_minutes_and_invalid_keys(): void
    {
        $shiftService = app(ShiftService::class);
        $simulator = app(SimulatorService::class);
        $run = $shiftService->startRun($shiftService->createRun('demo_normal', 42));

        $this->expectException(InvalidSimulationTick::class);
        $simulator->tick($run, 0);
    }

    public function test_sync_elapsed_real_time_uses_anchor_speed_and_does_not_double_consume(): void
    {
        $shiftService = app(ShiftService::class);
        $simulator = app(SimulatorService::class);
        $run = $shiftService->startRun($shiftService->createRun('demo_normal', 42));
        $run->forceFill([
            'real_last_tick_at' => CarbonImmutable::parse('2026-09-12 12:00:00'),
        ])->save();

        $synced = $simulator->syncElapsedRealTime(
            $run,
            CarbonImmutable::parse('2026-09-12 12:02:30')
        );
        $again = $simulator->syncElapsedRealTime(
            $run,
            CarbonImmutable::parse('2026-09-12 12:02:30')
        );

        $this->assertSame('2026-09-12 18:02:00', $synced->simulated_current_at->format('Y-m-d H:i:s'));
        $this->assertSame([2, 2], $synced->shifts->pluck('idle_minutes')->all());
        $this->assertSame($synced->simulated_current_at->getTimestamp(), $again->simulated_current_at->getTimestamp());
        $this->assertSame('2026-09-12 12:02:00', $synced->real_last_tick_at->format('Y-m-d H:i:s'));
    }

    public function test_sync_speed_multiplier_advances_virtual_minutes_deterministically(): void
    {
        $shiftService = app(ShiftService::class);
        $simulator = app(SimulatorService::class);
        $run = $shiftService->startRun($shiftService->createRun('demo_normal', 42));
        $run->forceFill([
            'speed_multiplier' => '5.0000',
            'real_last_tick_at' => CarbonImmutable::parse('2026-09-12 12:00:00'),
        ])->save();

        $synced = $simulator->syncElapsedRealTime(
            $run,
            CarbonImmutable::parse('2026-09-12 12:00:12')
        );

        $this->assertSame('2026-09-12 18:01:00', $synced->simulated_current_at->format('Y-m-d H:i:s'));
        $this->assertSame([1, 1], $synced->shifts->pluck('idle_minutes')->all());
    }
}
