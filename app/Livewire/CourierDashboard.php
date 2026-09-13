<?php

namespace App\Livewire;

use App\DTOs\OptimizedPlan;
use App\Enums\AgentType;
use App\Models\Recommendation;
use App\Models\Shift;
use App\Models\SimulationRun;
use App\Services\PlanExecutionService;
use App\Services\RecommendationService;
use App\Services\ShiftService;
use App\Services\SimulatorService;
use App\Support\MapPresenter;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class CourierDashboard extends Component
{
    public string $scenarioKey = 'demo_normal';

    public int $seed = 42;

    public int $tickMinutes = 5;

    public ?int $runId = null;

    public ?int $shiftId = null;

    public string $status = 'IDLE';

    public ?string $simulatedCurrentAt = null;

    public ?string $simulatedEndsAt = null;

    public array $shifts = [];

    public array $offers = [];

    public array $mapData = ['type' => 'FeatureCollection', 'features' => []];

    public array $recentEvents = [];

    public ?string $eventBanner = null;

    public ?array $recommendation = null;

    public string $errorMessage = '';

    public string $successMessage = '';

    public bool $loading = false;

    public int $tickSequence = 0;

    public function startShift(ShiftService $shiftService): void
    {
        $this->runId = null;
        $this->shiftId = null;
        $this->recommendation = null;
        $this->clearMessages();
        try {
            $run = $shiftService->createRun($this->scenarioKey, $this->seed);
            $run = $shiftService->startRun($run);
            $this->runId = $run->id;
            $this->successMessage = 'Turno iniciado. Avanza el reloj para recibir pedidos.';
            $this->refreshState($shiftService);
        } catch (\Throwable $exception) {
            $this->errorMessage = $exception->getMessage();
        }
    }

    public function pauseShift(ShiftService $shiftService): void
    {
        $this->transition($shiftService, 'pauseRun', 'Turno pausado.');
    }

    public function resumeShift(ShiftService $shiftService): void
    {
        $this->transition($shiftService, 'resumeRun', 'Turno reanudado.');
    }

    public function manualTick(SimulatorService $simulator, ShiftService $shiftService): void
    {
        if ($this->runId === null) {
            return;
        }
        $this->clearMessages();
        try {
            $this->tickSequence++;
            $run = $simulator->tick($this->runId, max(1, $this->tickMinutes), 'dashboard-tick-'.$this->runId.'-'.$this->tickSequence);
            $this->refreshState($shiftService, $run);
        } catch (\Throwable $exception) {
            $this->errorMessage = $exception->getMessage();
        }
    }

    public function poll(SimulatorService $simulator, ShiftService $shiftService): void
    {
        if ($this->runId === null || $this->status !== 'RUNNING') {
            return;
        }
        try {
            $run = $simulator->syncElapsedRealTime($this->runId);
            $this->refreshState($shiftService, $run, false);
        } catch (\Throwable $exception) {
            $this->errorMessage = $exception->getMessage();
        }
    }

    public function generateRecommendation(RecommendationService $recommendations, ShiftService $shiftService): void
    {
        if ($this->runId === null || $this->shiftId === null) {
            return;
        }
        $this->clearMessages();
        try {
            $result = $recommendations->recommend(Shift::findOrFail($this->shiftId), SimulationRun::findOrFail($this->runId));
            $this->recommendation = [
                'id' => $result['recommendation']->id,
                'ranking' => array_map(static fn ($item): array => $item->toArray(), $result['ranking']),
                'plan' => $result['plan']->toArray(),
                'facts' => $result['facts'],
                'explanation' => $result['recommendation']->deterministic_explanation,
                'explanation_source' => $result['explanation_source'] ?? 'deterministic',
                'llm_explanation' => $result['llm_explanation'] ?? null,
            ];
            $this->successMessage = 'Recomendación calculada con reglas deterministas.';
            $this->refreshState($shiftService);
        } catch (\Throwable $exception) {
            $this->errorMessage = $exception->getMessage();
        }
    }

    public function acceptRecommendation(PlanExecutionService $execution, ShiftService $shiftService): void
    {
        if ($this->runId === null || $this->shiftId === null || $this->recommendation === null) {
            return;
        }
        $this->clearMessages();
        try {
            $shift = Shift::findOrFail($this->shiftId);
            $recommendation = Recommendation::findOrFail($this->recommendation['id']);
            $plan = OptimizedPlan::fromArray($this->recommendation['plan']);
            $execution->acceptAndExecute($shift, SimulationRun::findOrFail($this->runId), $plan, $recommendation, 'dashboard-accept-'.$recommendation->id);
            $this->successMessage = 'Plan aceptado y entrega iniciada.';
            $this->refreshState($shiftService);
        } catch (\Throwable $exception) {
            $this->errorMessage = $exception->getMessage();
        }
    }

    public function refresh(ShiftService $shiftService): void
    {
        $this->refreshState($shiftService);
    }

    #[On('simulation-refresh')]
    public function refreshFromBroadcast(ShiftService $shiftService): void
    {
        if ($this->runId !== null) {
            $this->refreshState($shiftService);
        }
    }

    public function render(): View
    {
        return view('livewire.courier-dashboard');
    }

    private function transition(ShiftService $shiftService, string $method, string $message): void
    {
        if ($this->runId === null) {
            return;
        }
        $this->clearMessages();
        try {
            $run = $shiftService->{$method}($this->runId);
            $this->successMessage = $message;
            $this->refreshState($shiftService, $run);
        } catch (\Throwable $exception) {
            $this->errorMessage = $exception->getMessage();
        }
    }

    private function refreshState(ShiftService $shiftService, ?SimulationRun $run = null, bool $keepRecommendation = true): void
    {
        if ($this->runId === null) {
            return;
        }
        $state = $shiftService->getRunState($run?->id ?? $this->runId);
        $run = $state['run'];
        $this->status = $run->status->value;
        $this->simulatedCurrentAt = $run->simulated_current_at?->toIso8601String();
        $this->simulatedEndsAt = $run->simulated_ends_at?->toIso8601String();
        $this->shifts = collect($state['shifts'])->map(fn (Shift $shift): array => [
            'id' => $shift->id,
            'agent_type' => $shift->agent_type->value,
            'status' => $shift->status->value,
            'gross' => (string) $shift->gross_earnings_mxn,
            'net' => (string) $shift->net_earnings_mxn,
            'distance' => (float) $shift->total_distance_km,
            'completed' => (int) $shift->completed_orders_count,
            'lat' => (float) $shift->current_lat,
            'lon' => (float) $shift->current_lon,
        ])->values()->all();
        $this->shiftId ??= collect($this->shifts)->firstWhere('agent_type', AgentType::COURIER_AI->value)['id'] ?? null;
        if ($this->shiftId === null) {
            return;
        }
        $shift = Shift::find($this->shiftId);
        if ($shift === null) {
            return;
        }
        $ranking = collect($this->recommendation['ranking'] ?? [])->keyBy('order_id');
        $this->offers = $shift->shiftOrders()->with('order')->get()->map(function ($shiftOrder) use ($ranking): array {
            $order = $shiftOrder->order;
            $score = $ranking->get($order?->external_id, []);

            return [
                'id' => $order?->external_id,
                'restaurant' => $order?->restaurant_name,
                'pay' => (string) (($order?->base_pay_mxn ?? 0) + ($order?->surge_bonus_mxn ?? 0) + ($order?->other_bonus_mxn ?? 0)),
                'distance' => $score['total_distance_km'] ?? null,
                'minutes' => $score['total_time_min'] ?? null,
                'net' => $score['net_profit_mxn'] ?? null,
                'hourly' => $score['net_hourly_rate_mxn'] ?? null,
                'risk' => $score['lateness_risk'] ?? null,
                'score' => $score['score'] ?? null,
                'status' => $shiftOrder->status->value,
                'recommended' => in_array($order?->external_id, $this->recommendation['plan']['order_ids'] ?? [], true),
            ];
        })->filter(fn (array $offer): bool => $offer['id'] !== null)->values()->all();
        $this->mapData = app(MapPresenter::class)->present($shift, $this->recommendation);
        $this->dispatch('map-updated', payload: $this->mapData);
        $this->dispatch('echo-subscribe', runId: $this->runId);
        $this->recentEvents = $run->events()->whereNotNull('applied_at')->latest('applied_at')->limit(5)->get()->map(fn ($event): array => ['id' => $event->id, 'type' => $event->type->value, 'applied_at' => $event->applied_at?->toIso8601String(), 'reason_codes' => $event->reason_codes ?? []])->values()->all();
        $latestEvent = $this->recentEvents[0] ?? null;
        $this->eventBanner = $latestEvent === null ? null : 'Evento simulado: '.str_replace('_', ' ', $latestEvent['type']).' aplicado a las '.date('H:i', strtotime($latestEvent['applied_at']));
    }

    private function clearMessages(): void
    {
        $this->errorMessage = '';
        $this->successMessage = '';
    }
}
