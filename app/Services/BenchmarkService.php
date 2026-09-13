<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\OptimizedPlan;
use App\Enums\AgentType;
use App\Enums\BenchmarkStatus;
use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Models\BenchmarkResult;
use App\Models\BenchmarkRun;
use App\Models\Shift;
use App\Models\SimulationRun;
use App\Support\Money;

final class BenchmarkService
{
    public function __construct(
        private readonly ShiftService $shiftService,
        private readonly SimulatorService $simulator,
        private readonly RecommendationService $recommendations,
        private readonly BaselineService $baseline,
        private readonly PlanExecutionService $execution,
    ) {}

    /** @param list<int> $seeds */
    public function create(string $scenarioKey, array $seeds, ?array $configSnapshot = null): BenchmarkRun
    {
        $normalizedSeeds = $this->normalizeSeeds($seeds);
        $snapshot = $configSnapshot ?? config('courier');

        return BenchmarkRun::query()->create([
            'scenario_key' => $scenarioKey,
            'seeds' => $normalizedSeeds,
            'config_snapshot' => $snapshot,
            'config_version' => (string) ($snapshot['benchmark']['algorithm_version'] ?? 'v1'),
            'algorithm_version' => (string) ($snapshot['benchmark']['algorithm_version'] ?? 'v1'),
            'status' => BenchmarkStatus::PENDING,
            'total_seeds' => count($normalizedSeeds),
        ]);
    }

    /** Execute or resume a benchmark idempotently. */
    public function run(BenchmarkRun|int $benchmark): BenchmarkRun
    {
        $benchmark = $benchmark instanceof BenchmarkRun
            ? $benchmark->fresh()
            : BenchmarkRun::query()->findOrFail($benchmark);
        if ($benchmark->status === BenchmarkStatus::COMPLETED && (int) $benchmark->failed_seeds === 0) {
            return $benchmark->load('results');
        }
        $benchmark->forceFill([
            'status' => BenchmarkStatus::RUNNING,
            'started_at' => $benchmark->started_at ?? now(),
        ])->save();

        foreach ((array) $benchmark->seeds as $seed) {
            if ($this->seedCompleted($benchmark, (int) $seed)) {
                continue;
            }
            try {
                $this->executeSeed($benchmark, (int) $seed);
            } catch (\Throwable $exception) {
                $this->recordFailure($benchmark, (int) $seed, $exception->getMessage());
            }
        }

        $completed = $this->completedSeedCount($benchmark);
        $failed = $this->failedSeedCount($benchmark);
        $terminal = $completed + $failed >= (int) $benchmark->total_seeds;
        $benchmark->forceFill([
            'completed_seeds' => $completed,
            'failed_seeds' => $failed,
            'status' => $terminal ? ($completed > 0 ? BenchmarkStatus::COMPLETED : BenchmarkStatus::FAILED) : BenchmarkStatus::RUNNING,
            'finished_at' => $terminal ? now() : null,
        ])->save();

        return $benchmark->fresh('results');
    }

    public function resume(BenchmarkRun|int $benchmark): BenchmarkRun
    {
        return $this->run($benchmark);
    }

    /** @return array<string, mixed> */
    public function summary(BenchmarkRun|int $benchmark): array
    {
        $benchmark = $benchmark instanceof BenchmarkRun ? $benchmark->load('results') : BenchmarkRun::query()->with('results')->findOrFail($benchmark);
        $results = $benchmark->results;
        $byAgent = [];
        foreach (AgentType::cases() as $agent) {
            $agentResults = $results->where('agent_type', $agent)->where('status', BenchmarkStatus::COMPLETED)->values();
            $byAgent[$agent->value] = $this->aggregate($agentResults);
        }
        $paired = $results->where('status', BenchmarkStatus::COMPLETED)->groupBy('seed')->filter(fn ($items): bool => $items->pluck('agent_type')->unique()->count() === 2);
        $comparisons = $paired->map(function ($items, $seed): array {
            $ai = $items->firstWhere('agent_type', AgentType::COURIER_AI);
            $baseline = $items->firstWhere('agent_type', AgentType::BASELINE);
            $delta = (float) $ai->net_earnings_mxn - (float) $baseline->net_earnings_mxn;

            return [
                'seed' => (int) $seed,
                'courier_ai_net_profit' => (string) $ai->net_earnings_mxn,
                'baseline_net_profit' => (string) $baseline->net_earnings_mxn,
                'delta_mxn' => Money::format($delta),
                'improvement_percent' => $this->improvementPercent((string) $ai->net_earnings_mxn, (string) $baseline->net_earnings_mxn),
                'winner' => $delta > 0 ? AgentType::COURIER_AI->value : ($delta < 0 ? AgentType::BASELINE->value : 'TIE'),
            ];
        })->values()->all();
        $wins = collect($comparisons)->where('winner', AgentType::COURIER_AI->value)->count();
        $pairedCount = count($comparisons);
        $failedSeedIds = $results->where('status', BenchmarkStatus::FAILED)->pluck('seed')->unique()->sort()->values()->all();
        $failedDetails = $results->where('status', BenchmarkStatus::FAILED)->groupBy('seed')->map(fn ($items): string => (string) ($items->first()->error_message ?? 'Error no especificado'))->all();

        return [
            'benchmark_id' => $benchmark->id,
            'scenario_key' => $benchmark->scenario_key,
            'seeds' => $benchmark->seeds,
            'config_version' => $benchmark->config_version,
            'algorithm_version' => $benchmark->algorithm_version,
            'status' => $benchmark->status->value,
            'completed_seeds' => (int) $benchmark->completed_seeds,
            'failed_seeds' => (int) $benchmark->failed_seeds,
            'failed_seed_ids' => array_map('intval', $failedSeedIds),
            'failed_details' => $failedDetails,
            'agents' => $byAgent,
            'comparisons' => $comparisons,
            'courier_ai_win_rate_percent' => $pairedCount === 0 ? null : round($wins / $pairedCount * 100, 2),
            'is_simulated' => true,
        ];
    }

    /** @return array<string, mixed> */
    public function simulationSummary(SimulationRun $run): array
    {
        $agents = [];
        foreach ($run->load('shifts')->shifts as $shift) {
            $agents[$shift->agent_type->value] = $this->metricsForShift($shift, $run);
        }
        $ai = $agents[AgentType::COURIER_AI->value] ?? null;
        $baseline = $agents[AgentType::BASELINE->value] ?? null;
        $improvement = $ai !== null && $baseline !== null
            ? $this->improvementPercent($ai['net_earnings_mxn'], $baseline['net_earnings_mxn'])
            : null;

        return [
            'scenario_key' => $run->scenario_key,
            'seed' => (int) $run->seed,
            'config_version' => (string) ($run->config_snapshot['benchmark']['algorithm_version'] ?? 'v1'),
            'agents' => $agents,
            'improvement_percent' => $improvement,
            'is_simulated' => true,
        ];
    }

    public function improvementPercent(string|float $courierAiNet, string|float $baselineNet): ?float
    {
        $baseline = (float) $baselineNet;
        if (abs($baseline) < 0.000001) {
            return null;
        }

        return round((((float) $courierAiNet - $baseline) / $baseline) * 100, 2);
    }

    /** @return array<string, mixed> */
    public function metricsForShift(Shift $shift, SimulationRun $run): array
    {
        $shift = $shift->fresh();
        $totalMinutes = max(0, (int) $shift->active_minutes + (int) $shift->idle_minutes);
        $statuses = $shift->shiftOrders()->pluck('status');
        $accepted = $statuses->filter(fn ($status): bool => in_array($status, [OrderStatus::ACCEPTED->value, OrderStatus::PICKING_UP->value, OrderStatus::PICKED_UP->value, OrderStatus::DELIVERING->value, OrderStatus::DELIVERED->value], true))->count();
        $rejected = $statuses->filter(fn ($status): bool => in_array($status, [OrderStatus::REJECTED->value, OrderStatus::EXPIRED->value], true))->count();
        $productive = $totalMinutes > 0 ? round(((int) $shift->active_minutes / $totalMinutes) * 100, 2) : 0.0;
        $hourly = $totalMinutes > 0 ? Money::format(((float) $shift->net_earnings_mxn / $totalMinutes) * 60) : '0.00';
        $latestRecommendation = $shift->recommendations()->latest('id')->first();
        $routing = is_array($latestRecommendation?->metrics) ? $latestRecommendation->metrics : [];

        return [
            'simulation_run_id' => $run->id,
            'agent_type' => $shift->agent_type->value,
            'gross_earnings_mxn' => (string) $shift->gross_earnings_mxn,
            'operating_cost_mxn' => (string) $shift->operating_cost_mxn,
            'net_earnings_mxn' => (string) $shift->net_earnings_mxn,
            'net_hourly_rate_mxn' => $hourly,
            'total_distance_km' => (float) $shift->total_distance_km,
            'deadhead_distance_km' => (float) $shift->deadhead_distance_km,
            'active_minutes' => (int) $shift->active_minutes,
            'idle_minutes' => (int) $shift->idle_minutes,
            'accepted_orders_count' => $accepted,
            'rejected_orders_count' => $rejected,
            'completed_orders_count' => (int) $shift->completed_orders_count,
            'late_orders_count' => (int) $shift->late_orders_count,
            'productive_time_percent' => $productive,
            'duration_minutes' => $totalMinutes,
            'routing_fallback' => (bool) ($routing['routing_fallback'] ?? false),
            'routing_provider' => $routing['route_provider'] ?? null,
            'algorithm_version' => $run->config_snapshot['benchmark']['algorithm_version'] ?? 'v1',
            'simulated_started_at' => $run->simulated_started_at?->toIso8601String(),
            'simulated_finished_at' => $run->simulated_current_at?->toIso8601String(),
            'is_simulated' => true,
        ];
    }

    private function executeSeed(BenchmarkRun $benchmark, int $seed): void
    {
        $simulation = $this->shiftService->startRun($this->shiftService->createRun($benchmark->scenario_key, $seed, configSnapshot: $benchmark->config_snapshot));
        $maxIterations = max(1, (int) $simulation->simulated_started_at->diffInMinutes($simulation->simulated_ends_at) + 2);
        $tickMinutes = max(1, (int) config('courier.benchmark.tick_minutes', 5));
        for ($iteration = 0; $iteration < $maxIterations && $simulation->status->value === 'RUNNING'; $iteration++) {
            $simulation = $simulation->fresh('shifts');
            foreach ($simulation->shifts as $shift) {
                $this->executeIfIdle($shift, $simulation);
            }
            $remaining = max(1, (int) $simulation->simulated_current_at->diffInMinutes($simulation->simulated_ends_at));
            $simulation = $this->simulator->tick($simulation, min($tickMinutes, $remaining), 'benchmark-'.$benchmark->id.'-'.$seed.'-'.$iteration);
        }
        $simulation = $simulation->fresh('shifts');
        if ($simulation->status->value !== 'FINISHED') {
            $simulation = $this->shiftService->finishRun($simulation);
        }
        foreach ($simulation->shifts as $shift) {
            $metrics = $this->metricsForShift($shift, $simulation);
            BenchmarkResult::query()->updateOrCreate(
                ['benchmark_run_id' => $benchmark->id, 'seed' => $seed, 'agent_type' => $shift->agent_type],
                $metrics + ['status' => BenchmarkStatus::COMPLETED, 'error_message' => null],
            );
        }
    }

    private function executeIfIdle(Shift $shift, SimulationRun $run): void
    {
        if ($shift->deliveries()->where('status', DeliveryStatus::IN_PROGRESS->value)->exists()) {
            return;
        }
        if ($shift->agent_type === AgentType::COURIER_AI) {
            $result = $this->recommendations->recommend($shift, $run);
            if ($result['plan']->feasible && $result['plan']->orderIds !== []) {
                $this->execution->acceptAndExecute($shift, $run, $result['plan'], $result['recommendation'], 'benchmark-ai-'.$run->id.'-'.$shift->id.'-'.$run->simulated_current_at->timestamp);
            }

            return;
        }
        $plan = $this->baseline->selectPlan($shift, $run);
        if ($plan instanceof OptimizedPlan && $plan->feasible && $plan->orderIds !== []) {
            $this->execution->acceptAndExecute($shift, $run, $plan, null, 'benchmark-baseline-'.$run->id.'-'.$shift->id.'-'.$run->simulated_current_at->timestamp);
        }
    }

    private function seedCompleted(BenchmarkRun $benchmark, int $seed): bool
    {
        return $benchmark->results()->where('seed', $seed)->where('status', BenchmarkStatus::COMPLETED->value)->distinct('agent_type')->count('agent_type') === 2;
    }

    private function recordFailure(BenchmarkRun $benchmark, int $seed, string $message): void
    {
        foreach (AgentType::cases() as $agent) {
            BenchmarkResult::query()->updateOrCreate(
                ['benchmark_run_id' => $benchmark->id, 'seed' => $seed, 'agent_type' => $agent],
                ['status' => BenchmarkStatus::FAILED, 'error_message' => mb_substr($message, 0, 1000), 'simulation_run_id' => null, 'metrics' => ['is_simulated' => true]],
            );
        }
    }

    private function completedSeedCount(BenchmarkRun $benchmark): int
    {
        return $benchmark->results()->where('status', BenchmarkStatus::COMPLETED->value)->select('seed')->groupBy('seed')->havingRaw('COUNT(DISTINCT agent_type) = 2')->get()->count();
    }

    private function failedSeedCount(BenchmarkRun $benchmark): int
    {
        return $benchmark->results()->where('status', BenchmarkStatus::FAILED->value)->select('seed')->distinct()->get()->count();
    }

    /** @param iterable<BenchmarkResult> $results @return array<string, mixed> */
    private function aggregate(iterable $results): array
    {
        $collection = collect($results);
        $mean = static fn (string $field): float => round($collection->avg(fn (BenchmarkResult $item): float => (float) $item->{$field}) ?? 0, 2);
        $median = static function (string $field) use ($collection): float {
            $values = $collection->map(fn (BenchmarkResult $item): float => (float) $item->{$field})->sort()->values()->all();
            $count = count($values);
            if ($count === 0) {
                return 0.0;
            }
            $middle = intdiv($count, 2);

            return round($count % 2 === 0 ? (($values[$middle - 1] + $values[$middle]) / 2) : $values[$middle], 2);
        };

        return [
            'sample_size' => $collection->count(),
            'mean' => ['gross_earnings_mxn' => $mean('gross_earnings_mxn'), 'operating_cost_mxn' => $mean('operating_cost_mxn'), 'net_earnings_mxn' => $mean('net_earnings_mxn'), 'net_hourly_rate_mxn' => $mean('net_hourly_rate_mxn'), 'total_distance_km' => $mean('total_distance_km'), 'deadhead_distance_km' => $mean('deadhead_distance_km'), 'active_minutes' => $mean('active_minutes'), 'idle_minutes' => $mean('idle_minutes'), 'accepted_orders_count' => $mean('accepted_orders_count'), 'rejected_orders_count' => $mean('rejected_orders_count'), 'completed_orders_count' => $mean('completed_orders_count'), 'late_orders_count' => $mean('late_orders_count'), 'productive_time_percent' => $mean('productive_time_percent')],
            'median' => ['net_earnings_mxn' => $median('net_earnings_mxn'), 'net_hourly_rate_mxn' => $median('net_hourly_rate_mxn'), 'total_distance_km' => $median('total_distance_km'), 'deadhead_distance_km' => $median('deadhead_distance_km'), 'active_minutes' => $median('active_minutes'), 'idle_minutes' => $median('idle_minutes'), 'late_orders_count' => $median('late_orders_count'), 'productive_time_percent' => $median('productive_time_percent')],
        ];
    }

    /** @param list<int> $seeds @return list<int> */
    private function normalizeSeeds(array $seeds): array
    {
        $normalized = [];
        foreach ($seeds as $seed) {
            if (! is_int($seed) && ! (is_string($seed) && preg_match('/^\d+$/', $seed))) {
                throw new \InvalidArgumentException('Benchmark seeds must contain between 1 and 100 non-negative integers.');
            }
            $normalized[] = (int) $seed;
        }
        $seeds = array_values(array_unique($normalized));
        if ($seeds === [] || count($seeds) > 100 || min($seeds) < 0) {
            throw new \InvalidArgumentException('Benchmark seeds must contain between 1 and 100 non-negative integers.');
        }

        return $seeds;
    }
}
