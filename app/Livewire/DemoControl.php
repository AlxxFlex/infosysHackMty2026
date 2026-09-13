<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\EventType;
use App\Enums\ShiftStatus;
use App\Models\SimulationRun;
use App\Services\ShiftService;
use App\Services\SimulationEventService;
use App\Services\SimulatorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

final class DemoControl extends Component
{
    public int $seed = 1901;

    public int $runId = 0;

    public string $status = 'IDLE';

    public ?string $currentAt = null;

    public int $speed = 1;

    public int $offersCount = 0;

    public string $message = '';

    public string $error = '';

    public bool $confirmReset = false;

    /** @var list<string> */
    public array $audit = [];

    public function mount(): void
    {
        $configuredToken = trim((string) config('courier.demo.access_token', ''));
        $providedToken = (string) request()->header('X-Demo-Token', request()->query('token', ''));
        abort_unless(app()->environment(['local', 'testing']) || ($configuredToken !== '' && hash_equals($configuredToken, $providedToken)), 404);
    }

    public function start(ShiftService $shifts): void
    {
        if ($this->runId !== 0) {
            return;
        }
        $this->clearMessages();
        try {
            $existing = SimulationRun::query()
                ->where('scenario_key', 'demo_normal')
                ->where('seed', $this->seed)
                ->whereIn('status', [ShiftStatus::IDLE->value, ShiftStatus::RUNNING->value, ShiftStatus::PAUSED->value])
                ->latest('id')
                ->first();
            $run = $existing === null ? $shifts->startRun($shifts->createRun('demo_normal', $this->seed)) : $existing;
            if ($existing !== null && $existing->status === ShiftStatus::IDLE) {
                $run = $shifts->startRun($existing);
            }
            $this->record('start');
            $this->sync($run);
            $this->message = "Escenario curado iniciado con {$this->offersCount} ofertas y seed {$this->seed}.";
        } catch (\Throwable $exception) {
            $this->error = $exception->getMessage();
        }
    }

    public function pause(ShiftService $shifts): void
    {
        $this->transition($shifts, 'pauseRun', 'Turno pausado.');
    }

    public function resume(ShiftService $shifts): void
    {
        $this->transition($shifts, 'resumeRun', 'Turno reanudado.');
    }

    public function setSpeed(int $speed): void
    {
        if (! in_array($speed, [1, 5, 20], true) || $this->runId === 0) {
            return;
        }
        $run = SimulationRun::query()->find($this->runId);
        if ($run === null || ! in_array($run->status, [ShiftStatus::RUNNING, ShiftStatus::PAUSED], true)) {
            return;
        }
        $run->forceFill(['speed_multiplier' => (string) $speed])->save();
        $this->speed = $speed;
        $this->record('speed-x'.$speed);
        $this->message = "Velocidad x{$speed} configurada.";
    }

    public function tick(SimulatorService $simulator, ShiftService $shifts): void
    {
        if ($this->runId === 0 || $this->status !== ShiftStatus::RUNNING->value) {
            return;
        }
        $this->clearMessages();
        try {
            $run = $simulator->tick($this->runId, $this->speed, 'demo-control-'.$this->runId.'-'.($this->currentAt ?? 'start').'-'.$this->speed);
            $this->record('tick-'.$this->speed);
            $this->sync($run);
            $this->message = "Tick de {$this->speed} minuto(s) aplicado.";
        } catch (\Throwable $exception) {
            $this->error = $exception->getMessage();
        }
    }

    public function triggerSurge(SimulationEventService $events): void
    {
        $this->scheduleEvent($events, EventType::SURGE_STARTED, ['zone' => 'TEC', 'multiplier' => 1.5], 'surge');
    }

    public function triggerClosure(SimulationEventService $events): void
    {
        $this->scheduleEvent($events, EventType::ROAD_CLOSED, ['closure_id' => 'DEMO-CLOSURE-'.$this->runId], 'cierre');
    }

    public function addRestaurantDelay(SimulationEventService $events): void
    {
        if ($this->runId === 0) {
            return;
        }
        $run = SimulationRun::query()->with('orders')->find($this->runId);
        $restaurantId = $run?->orders->first()?->restaurant_id;
        if ($restaurantId === null) {
            $this->error = 'No hay restaurante disponible para simular retraso.';

            return;
        }
        $this->scheduleEvent($events, EventType::RESTAURANT_DELAY_CHANGED, ['restaurant_id' => $restaurantId, 'wait_min' => 12], 'retraso');
    }

    public function finish(ShiftService $shifts): void
    {
        if ($this->runId === 0 || ! in_array($this->status, [ShiftStatus::RUNNING->value, ShiftStatus::PAUSED->value], true)) {
            return;
        }
        $this->clearMessages();
        try {
            $run = $shifts->finishRun($this->runId);
            $this->record('finish');
            $this->sync($run);
            $this->message = 'Turno terminado; comparación disponible.';
        } catch (\Throwable $exception) {
            $this->error = $exception->getMessage();
        }
    }

    public function requestReset(): void
    {
        if ($this->runId !== 0) {
            $this->confirmReset = true;
        }
    }

    public function resetRun(): void
    {
        if ($this->runId === 0 || ! $this->confirmReset) {
            return;
        }
        $runId = $this->runId;
        DB::transaction(fn () => SimulationRun::query()->whereKey($runId)->delete());
        Log::info('demo.control.reset', ['run_id' => $runId]);
        $this->runId = 0;
        $this->status = 'IDLE';
        $this->currentAt = null;
        $this->offersCount = 0;
        $this->confirmReset = false;
        $this->record('reset-'.$runId);
        $this->message = 'Run actual reiniciado de forma segura.';
    }

    public function newSeed(): void
    {
        if ($this->runId !== 0) {
            return;
        }
        $this->seed = random_int(1, 999999);
        $this->message = "Nueva seed: {$this->seed}.";
    }

    public function render()
    {
        return view('livewire.demo-control');
    }

    private function transition(ShiftService $shifts, string $method, string $message): void
    {
        if ($this->runId === 0) {
            return;
        }
        $this->clearMessages();
        try {
            $run = $shifts->{$method}($this->runId);
            $this->record($method);
            $this->sync($run);
            $this->message = $message;
        } catch (\Throwable $exception) {
            $this->error = $exception->getMessage();
        }
    }

    /** @param array<string, mixed> $payload */
    private function scheduleEvent(SimulationEventService $events, EventType $type, array $payload, string $label): void
    {
        if ($this->runId === 0 || $this->status !== ShiftStatus::RUNNING->value) {
            return;
        }
        $this->clearMessages();
        try {
            $run = SimulationRun::query()->findOrFail($this->runId);
            $when = $run->simulated_current_at->addMinute();
            if ($when->greaterThan($run->simulated_ends_at)) {
                throw new \RuntimeException('No se puede programar un evento después del final del turno.');
            }
            DB::transaction(function () use ($events, $run, $type, $when, $payload, $label): void {
                $lockedRun = SimulationRun::query()->lockForUpdate()->findOrFail($run->id);
                $alreadyScheduled = $lockedRun->events()->where('type', $type->value)->get()->contains(function ($event) use ($payload): bool {
                    foreach ($payload as $key => $value) {
                        if (($event->payload[$key] ?? null) !== $value) {
                            return false;
                        }
                    }

                    return true;
                });
                if ($alreadyScheduled) {
                    $this->message = "Evento {$label} ya estaba programado.";

                    return;
                }
                $events->schedule($lockedRun, $type, $when, $payload + ['is_simulated' => true]);
            });
            $this->record($label);
            $this->message = "Evento {$label} programado para el siguiente tick.";
        } catch (\Throwable $exception) {
            $this->error = $exception->getMessage();
        }
    }

    private function sync(SimulationRun $run): void
    {
        $run = $run->fresh('orders');
        $this->runId = (int) $run->id;
        $this->status = $run->status->value;
        $this->currentAt = $run->simulated_current_at?->toIso8601String();
        $this->offersCount = $run->orders->count();
        $this->speed = (int) $run->speed_multiplier;
    }

    private function record(string $action): void
    {
        $this->audit = array_slice([...$this->audit, now()->format('H:i:s').' '.$action], -8);
        Log::info('demo.control.action', ['run_id' => $this->runId, 'action' => $action]);
    }

    private function clearMessages(): void
    {
        $this->message = '';
        $this->error = '';
    }
}
