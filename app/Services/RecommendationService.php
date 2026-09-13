<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\EnvironmentState;
use App\DTOs\EvaluatedOrder;
use App\DTOs\OptimizedPlan;
use App\Enums\RecommendationStatus;
use App\Jobs\GenerateLlmExplanation;
use App\Models\Recommendation;
use App\Models\Shift;
use App\Models\SimulationRun;
use App\Support\StateMapper;
use DateTimeImmutable;
use DateTimeInterface;

final class RecommendationService
{
    public function __construct(
        private readonly StateMapper $stateMapper,
        private readonly ScoringService $scoringService,
        private readonly OptimizationService $optimizationService,
        private readonly ExplanationService $explanationService,
    ) {}

    /** @return list<EvaluatedOrder> */
    public function rankAvailable(Shift $shift, SimulationRun $run, ?DateTimeInterface $at = null): array
    {
        $run = $run->fresh();
        $at ??= $run->simulated_current_at;
        $environment = $this->environmentAt($run, $at);
        $courier = $this->stateMapper->toCourierState($shift->fresh(), $run);
        $orders = $shift->shiftOrders()
            ->with('order')
            ->available()
            ->get()
            ->pluck('order')
            ->filter()
            ->values();

        return $this->scoringService->rank($orders, $courier, $environment);
    }

    /** @return list<EvaluatedOrder> */
    public function rank(Shift $shift, SimulationRun $run, ?DateTimeInterface $at = null): array
    {
        return $this->rankAvailable($shift, $run, $at);
    }

    public function optimizeAvailable(Shift $shift, SimulationRun $run, ?DateTimeInterface $at = null): OptimizedPlan
    {
        $run = $run->fresh();
        $environment = $this->environmentAt($run, $at ?? $run->simulated_current_at);
        $courier = $this->stateMapper->toCourierState($shift->fresh(), $run);
        $orders = $shift->shiftOrders()->with('order')->available()->get()->pluck('order')->filter()->values();

        return $this->optimizationService->optimize($courier, $environment, $orders);
    }

    /** @return array{ranking: list<EvaluatedOrder>, plan: OptimizedPlan, alternatives: list<EvaluatedOrder>, facts: array<string, mixed>, recommendation: Recommendation} */
    public function recommend(Shift $shift, SimulationRun $run, ?DateTimeInterface $at = null): array
    {
        $run = $run->fresh();
        $shift = $shift->fresh();
        $rankingStartedAt = hrtime(true);
        $ranking = $this->rankAvailable($shift, $run, $at);
        $routingMs = (int) round((hrtime(true) - $rankingStartedAt) / 1_000_000);
        $startedAt = hrtime(true);
        $plan = $this->optimizeAvailable($shift, $run, $at);
        $optimizationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
        $selectedScore = collect($ranking)->first(fn (EvaluatedOrder $item): bool => in_array($item->orderId, $plan->orderIds, true))?->score;
        $configVersion = (string) config('courier.benchmark.algorithm_version', 'v1');
        $environment = $this->environmentAt($run, $at ?? $run->simulated_current_at);
        $facts = $this->explanationService->buildFacts($plan, $ranking, [
            'config_version' => $configVersion,
            'simulation_time' => $environment->simulationTime->format(DATE_ATOM),
            'estimated_demand' => collect($ranking)->whereIn('orderId', $plan->orderIds)->avg('destinationDemandScore'),
            'environment' => [
                'weather' => $environment->weather,
                'traffic_factor' => $environment->trafficFactor,
                'closure_version' => $environment->closureVersion,
                'surge_zones' => $environment->surgeZones,
                'road_closures' => $environment->roadClosures,
            ],
        ]);
        $deterministicExplanation = $this->explanationService->deterministic($facts);
        $recommendation = $shift->recommendations()->create([
            'simulation_time' => $at ?? $run->simulated_current_at,
            'config_version' => $configVersion,
            'ranking' => array_map(static fn (EvaluatedOrder $item): array => $item->toArray(), $ranking),
            'selected_order_ids' => $plan->orderIds,
            'route_sequence' => $plan->sequence,
            'metrics' => $plan->toArray() + ['explanation_facts' => $facts, 'is_simulated' => true],
            'reason_codes' => array_map(static fn ($reason): string => $reason->value, $plan->reasonCodes),
            'visual_score' => $selectedScore,
            'absolute_utility' => $plan->absoluteUtilityMxn,
            'expected_net_profit_mxn' => $plan->expectedNetProfitMxn,
            'expected_minutes' => $plan->expectedMinutes,
            'expected_distance_km' => $plan->expectedDistanceKm,
            'expected_hourly_rate_mxn' => $plan->netHourlyRateMxn,
            'estimated_risk' => $plan->risk,
            'status' => RecommendationStatus::PENDING,
            'deterministic_explanation' => $deterministicExplanation,
            'llm_explanation' => null,
            'routing_duration_ms' => $routingMs,
            'optimization_duration_ms' => $optimizationMs,
            'candidates_evaluated' => $plan->candidatesEvaluated,
        ]);
        if ($this->explanationService->providerName() !== 'none') {
            GenerateLlmExplanation::dispatch($recommendation->id, $facts)->afterCommit();
        }

        return [
            'ranking' => $ranking,
            'plan' => $plan,
            'alternatives' => array_slice($ranking, 0, 3),
            'facts' => $facts,
            'explanation_source' => 'deterministic',
            'llm_explanation' => null,
            'recommendation' => $recommendation,
        ];
    }

    private function environmentAt(SimulationRun $run, DateTimeInterface $at): EnvironmentState
    {
        $environment = $this->stateMapper->toEnvironmentState($run);

        return new EnvironmentState(
            simulationTime: DateTimeImmutable::createFromInterface($at),
            weather: $environment->weather,
            trafficFactor: $environment->trafficFactor,
            surgeZones: $environment->surgeZones,
            roadClosures: $environment->roadClosures,
            closureVersion: $environment->closureVersion,
        );
    }
}
