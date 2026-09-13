<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Coordinates;
use App\DTOs\CourierState;
use App\DTOs\EnvironmentState;
use App\DTOs\EvaluatedOrder;
use App\DTOs\OptimizedPlan;
use App\Enums\ReasonCode;
use App\Models\Order;
use App\Support\Money;
use Carbon\CarbonImmutable;

final class OptimizationService
{
    public function __construct(
        private readonly ScoringService $scoringService,
        private readonly RoutingService $routingService,
    ) {}

    public function optimize(
        CourierState $courier,
        EnvironmentState $environment,
        iterable $orders,
    ): OptimizedPlan {
        $startedAt = hrtime(true);
        $orderList = [];
        foreach ($orders as $order) {
            if ($order instanceof Order) {
                $orderList[] = $order;
            }
        }
        $evaluated = [];
        foreach ($orderList as $order) {
            $evaluated[$order->external_id] = $this->scoringService->evaluate($order, $courier, $environment);
        }

        $feasible = array_filter($evaluated, static fn (EvaluatedOrder $item): bool => $item->feasible);
        if ($feasible === []) {
            return $this->emptyPlan(false, false, 0);
        }

        $orderModels = [];
        foreach ($orderList as $order) {
            if (isset($feasible[$order->external_id])) {
                $orderModels[$order->external_id] = $order;
            }
        }

        $candidates = [];
        $ids = array_keys($orderModels);
        foreach ($ids as $id) {
            $candidates[] = [$id];
        }
        if ((int) config('courier.optimization.max_candidate_set_size', 2) >= 2 && $courier->maxConcurrentOrders - count($courier->currentOrders) >= 2) {
            for ($left = 0; $left < count($ids); $left++) {
                for ($right = $left + 1; $right < count($ids); $right++) {
                    $candidates[] = [$ids[$left], $ids[$right]];
                }
            }
        }
        $maxCandidates = max(1, (int) config('courier.optimization.max_candidates', 100));
        $candidates = array_slice($candidates, 0, $maxCandidates);
        $budgetMs = max(1, (int) config('courier.optimization.time_budget_ms', 300));
        $best = null;
        $evaluatedCount = 0;
        $budgetExceeded = false;

        foreach ($candidates as $candidate) {
            if ($evaluatedCount > 0 && $this->elapsedMs($startedAt) >= $budgetMs) {
                $budgetExceeded = true;
                break;
            }
            $models = array_map(static fn (string $id): Order => $orderModels[$id], $candidate);
            foreach ($this->validSequences($models) as $sequence) {
                if ($evaluatedCount > 0 && $this->elapsedMs($startedAt) >= $budgetMs) {
                    $budgetExceeded = true;
                    break 2;
                }
                $evaluatedCount++;
                $plan = $this->evaluateSequence($sequence, $candidate, $orderModels, $evaluated, $courier, $environment, $budgetExceeded);
                if ($plan->feasible && ($best === null || $this->compare($plan, $best) < 0)) {
                    $best = $plan;
                }
            }
        }

        if ($best === null) {
            return $this->emptyPlan(false, $budgetExceeded, $evaluatedCount);
        }

        return new OptimizedPlan(
            feasible: $best->feasible,
            orderIds: $best->orderIds,
            sequence: $best->sequence,
            expectedNetProfitMxn: $best->expectedNetProfitMxn,
            futurePositionValueMxn: $best->futurePositionValueMxn,
            batchEfficiencyBonusMxn: $best->batchEfficiencyBonusMxn,
            expectedDelayCostMxn: $best->expectedDelayCostMxn,
            riskPenaltyMxn: $best->riskPenaltyMxn,
            idlePenaltyMxn: $best->idlePenaltyMxn,
            absoluteUtilityMxn: $best->absoluteUtilityMxn,
            expectedMinutes: $best->expectedMinutes,
            expectedDistanceKm: $best->expectedDistanceKm,
            netHourlyRateMxn: $best->netHourlyRateMxn,
            risk: $best->risk,
            reasonCodes: $best->reasonCodes,
            routeProvider: $best->routeProvider,
            routingFallback: $best->routingFallback,
            budgetExceeded: $budgetExceeded,
            candidatesEvaluated: $evaluatedCount,
            batchSavingsDistanceKm: $best->batchSavingsDistanceKm,
            detourRatio: $best->detourRatio,
            routeOverlap: $best->routeOverlap,
            deadheadDistanceKm: $best->deadheadDistanceKm,
        );
    }

    public function optimizeOrders(CourierState $courier, EnvironmentState $environment, iterable $orders): OptimizedPlan
    {
        return $this->optimize($courier, $environment, $orders);
    }

    /** @param list<Order> $orders @return list<list<array{action: string, order_id: string}>> */
    private function validSequences(array $orders): array
    {
        $tokens = [];
        foreach ($orders as $order) {
            $tokens[] = ['action' => 'PICKUP', 'order_id' => (string) $order->external_id];
            $tokens[] = ['action' => 'DROPOFF', 'order_id' => (string) $order->external_id];
        }
        $sequences = [];
        $this->sequencePermutations($tokens, [], [], $sequences);

        return $sequences;
    }

    /** @param list<array{action: string, order_id: string}> $remaining @param list<array{action: string, order_id: string}> $sequence @param list<string> $picked @param list<list<array{action: string, order_id: string}>> $result */
    private function sequencePermutations(array $remaining, array $sequence, array $picked, array &$result): void
    {
        if ($remaining === []) {
            $result[] = $sequence;

            return;
        }

        foreach ($remaining as $index => $token) {
            if ($token['action'] === 'DROPOFF' && ! in_array($token['order_id'], $picked, true)) {
                continue;
            }
            $next = $remaining;
            unset($next[$index]);
            $next = array_values($next);
            $nextPicked = $token['action'] === 'PICKUP' ? [...$picked, $token['order_id']] : $picked;
            $this->sequencePermutations($next, [...$sequence, $token], $nextPicked, $result);
        }
    }

    /** @param list<array{action: string, order_id: string}> $sequence @param array<string, Order> $orderModels @param array<string, EvaluatedOrder> $evaluated */
    private function evaluateSequence(array $sequence, array $candidate, array $orderModels, array $evaluated, CourierState $courier, EnvironmentState $environment, bool $budgetExceeded): OptimizedPlan
    {
        $ordersById = [];
        foreach ($candidate as $id) {
            $ordersById[$id] = $orderModels[$id];
        }
        $points = [$courier->position];
        foreach ($sequence as $stop) {
            $order = $ordersById[$stop['order_id']];
            $points[] = $stop['action'] === 'PICKUP'
                ? new Coordinates((float) $order->restaurant_lat, (float) $order->restaurant_lon)
                : new Coordinates((float) $order->customer_lat, (float) $order->customer_lon);
        }
        $matrix = $this->routingService->matrix($points, closureVersion: $environment->closureVersion, trafficBucket: $this->trafficBucket($environment->trafficFactor));
        $traffic = max(0.001, $environment->trafficFactor);
        $weather = $this->weatherFactor($environment->weather);
        $totalDistance = 0.0;
        $deadheadDistance = 0.0;
        $rawMinutes = 0.0;
        $elapsed = 0.0;
        $capacity = count($courier->currentOrders);
        $slacks = [];
        $wait = 0.0;
        $feasible = true;
        $reasons = [];
        foreach ($sequence as $index => $stop) {
            $segmentDistance = (float) $matrix['distances_km'][$index][$index + 1];
            $segmentMinutes = (float) $matrix['durations_minutes'][$index][$index + 1] * $traffic * $weather;
            $totalDistance += $segmentDistance;
            if ($index === 0 && $stop['action'] === 'PICKUP') {
                $deadheadDistance = $segmentDistance;
            }
            $rawMinutes += $segmentMinutes;
            $elapsed += $segmentMinutes;
            $order = $ordersById[$stop['order_id']];
            if ($stop['action'] === 'PICKUP') {
                $pickupDeadline = $order->pickup_deadline;
                $pickupSlack = $pickupDeadline === null ? 0.0 : ($pickupDeadline->getTimestamp() - CarbonImmutable::instance($environment->simulationTime)->addSeconds((int) round($elapsed * 60))->getTimestamp()) / 60;
                if ($pickupSlack < 0 || $order->expires_at->lessThanOrEqualTo(CarbonImmutable::instance($environment->simulationTime)->addSeconds((int) round($elapsed * 60)))) {
                    $feasible = false;
                    $reasons[] = ReasonCode::SHIFT_TOO_SHORT;
                }
                $capacity++;
                $elapsed += (float) $order->estimated_restaurant_wait_min;
                $wait += (float) $order->estimated_restaurant_wait_min;
            } else {
                $capacity--;
                $deadline = $order->delivery_deadline ?? $order->pickup_deadline;
                $slack = $deadline === null ? $courier->shiftRemainingMin - $elapsed : ($deadline->getTimestamp() - CarbonImmutable::instance($environment->simulationTime)->addSeconds((int) round($elapsed * 60))->getTimestamp()) / 60;
                $slacks[] = $slack;
                if ($slack < 0) {
                    $feasible = false;
                    $reasons[] = ReasonCode::SHIFT_TOO_SHORT;
                }
            }
            if ($capacity > $courier->maxConcurrentOrders || $capacity < 0) {
                $feasible = false;
                $reasons[] = ReasonCode::INCOMPATIBLE_BATCH;
            }
        }
        $totalTime = $rawMinutes + $wait;
        if ($totalTime > $courier->shiftRemainingMin + 0.000001) {
            $feasible = false;
            $reasons[] = ReasonCode::SHIFT_TOO_SHORT;
        }
        $individualNet = '0.00';
        $individualFuture = '0.00';
        $individualDistance = 0.0;
        $individualTime = 0.0;
        $risk = 0.0;
        foreach ($candidate as $id) {
            $item = $evaluated[$id];
            $individualNet = Money::add($individualNet, $item->netProfitMxn);
            $individualFuture = $item->futurePositionBonusMxn > $individualFuture ? $item->futurePositionBonusMxn : $individualFuture;
            $individualDistance += $item->totalDistanceKm;
            $individualTime += $item->totalTimeMin;
            $risk = max($risk, $item->latenessRisk);
            $reasons = [...$reasons, ...$item->reasonCodes];
        }
        $gross = '0.00';
        foreach ($candidate as $id) {
            $gross = Money::add($gross, $evaluated[$id]->grossPayMxn);
        }
        $cost = Money::multiply((string) $totalDistance, (float) $courier->costPerKmMxn);
        $net = Money::subtract($gross, $cost);
        $batchBonus = count($candidate) > 1 ? Money::multiply((string) max(0.0, $individualTime - $totalTime), (float) config('courier.optimization.batch_efficiency_per_min_mxn', 0.50)) : '0.00';
        $delayCost = Money::multiply((string) max(0.0, -min($slacks ?: [0.0])), (float) config('courier.optimization.delay_cost_per_min_mxn', 2.00));
        $riskPenalty = Money::multiply('1.00', $risk * (float) config('courier.optimization.risk_penalty_mxn', 10.00));
        $idlePenalty = Money::multiply((string) $totalTime, (float) config('courier.optimization.idle_penalty_per_min_mxn', 0.05));
        $utility = Money::subtract(Money::add($net, $individualFuture, $batchBonus), Money::add($delayCost, $riskPenalty, $idlePenalty));
        $batchSavings = count($candidate) > 1 ? max(0.0, $individualDistance - $totalDistance) : 0.0;
        $detourRatio = $individualDistance > 0 ? $totalDistance / $individualDistance : 0.0;
        $routeOverlap = $individualDistance > 0 ? max(0.0, min(1.0, $batchSavings / $individualDistance)) : 0.0;
        if (count($candidate) > 1) {
            $reasons[] = ReasonCode::BATCH_COMPATIBLE;
        }

        return new OptimizedPlan(
            feasible: $feasible,
            orderIds: array_values($candidate),
            sequence: $sequence,
            expectedNetProfitMxn: $net,
            futurePositionValueMxn: $individualFuture,
            batchEfficiencyBonusMxn: $batchBonus,
            expectedDelayCostMxn: $delayCost,
            riskPenaltyMxn: $riskPenalty,
            idlePenaltyMxn: $idlePenalty,
            absoluteUtilityMxn: $utility,
            expectedMinutes: $totalTime,
            expectedDistanceKm: $totalDistance,
            netHourlyRateMxn: Money::format($totalTime > 0 ? ((float) $net / $totalTime) * 60 : 0),
            risk: max(0.0, min(1.0, $risk)),
            reasonCodes: array_values(array_unique($reasons, SORT_REGULAR)),
            routeProvider: (string) ($matrix['provider'] ?? 'unknown'),
            routingFallback: ($matrix['is_fallback'] ?? false) === true,
            budgetExceeded: $budgetExceeded,
            batchSavingsDistanceKm: $batchSavings,
            detourRatio: $detourRatio,
            routeOverlap: $routeOverlap,
            deadheadDistanceKm: $deadheadDistance,
        );
    }

    private function compare(OptimizedPlan $left, OptimizedPlan $right): int
    {
        return ($right->absoluteUtilityMxn <=> $left->absoluteUtilityMxn)
            ?: ($right->netHourlyRateMxn <=> $left->netHourlyRateMxn)
            ?: ($left->risk <=> $right->risk)
            ?: strcmp(implode(',', $left->orderIds), implode(',', $right->orderIds));
    }

    private function emptyPlan(bool $feasible, bool $budgetExceeded, int $evaluated): OptimizedPlan
    {
        return new OptimizedPlan(
            feasible: $feasible,
            orderIds: [],
            sequence: [],
            expectedNetProfitMxn: '0.00',
            futurePositionValueMxn: '0.00',
            batchEfficiencyBonusMxn: '0.00',
            expectedDelayCostMxn: '0.00',
            riskPenaltyMxn: '0.00',
            idlePenaltyMxn: '0.00',
            absoluteUtilityMxn: '0.00',
            expectedMinutes: 0.0,
            expectedDistanceKm: 0.0,
            netHourlyRateMxn: '0.00',
            risk: 0.0,
            reasonCodes: [],
            routeProvider: 'none',
            routingFallback: false,
            budgetExceeded: $budgetExceeded,
            candidatesEvaluated: $evaluated,
            batchSavingsDistanceKm: 0.0,
            detourRatio: 0.0,
            routeOverlap: 0.0,
            deadheadDistanceKm: 0.0,
        );
    }

    private function elapsedMs(int $startedAt): float
    {
        return (hrtime(true) - $startedAt) / 1_000_000;
    }

    private function weatherFactor(string $weather): float
    {
        return max(0.001, (float) (config('courier.scoring.weather_factors.'.$weather) ?? 1.0));
    }

    private function trafficBucket(float $factor): string
    {
        $size = max(0.0001, (float) config('courier.routing.traffic_bucket_size', 0.10));

        return 'factor_'.number_format(floor($factor / $size) * $size, 2, '.', '');
    }
}
