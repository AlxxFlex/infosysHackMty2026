<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\EnvironmentState;
use App\DTOs\EvaluatedOrder;
use App\DTOs\OptimizedPlan;
use App\Models\Shift;
use App\Models\SimulationRun;
use App\Support\StateMapper;
use DateTimeInterface;

final class BaselineService
{
    public function __construct(
        private readonly StateMapper $stateMapper,
        private readonly ScoringService $scoringService,
    ) {}

    public function select(Shift $shift, SimulationRun $run, ?DateTimeInterface $at = null): ?EvaluatedOrder
    {
        $run = $run->fresh();
        $environment = $this->environmentAt($run, $at ?? $run->simulated_current_at);
        $courier = $this->stateMapper->toCourierState($shift->fresh(), $run);
        $orders = $shift->shiftOrders()->with('order')->available()->get()->pluck('order')->filter();
        $feasible = [];
        foreach ($orders as $order) {
            $evaluated = $this->scoringService->evaluate($order, $courier, $environment);
            if ($evaluated->feasible) {
                $feasible[] = $evaluated;
            }
        }
        usort($feasible, static fn (EvaluatedOrder $left, EvaluatedOrder $right): int => ((float) $right->grossPayMxn <=> (float) $left->grossPayMxn)
            ?: ($left->totalTimeMin <=> $right->totalTimeMin)
            ?: strcmp($left->orderId, $right->orderId));

        return $feasible[0] ?? null;
    }

    public function choose(Shift $shift, SimulationRun $run, ?DateTimeInterface $at = null): ?EvaluatedOrder
    {
        return $this->select($shift, $run, $at);
    }

    public function selectPlan(Shift $shift, SimulationRun $run, ?DateTimeInterface $at = null): ?OptimizedPlan
    {
        $selected = $this->select($shift, $run, $at);
        if ($selected === null) {
            return null;
        }

        return new OptimizedPlan(
            feasible: true,
            orderIds: [$selected->orderId],
            sequence: [
                ['action' => 'PICKUP', 'order_id' => $selected->orderId],
                ['action' => 'DROPOFF', 'order_id' => $selected->orderId],
            ],
            expectedNetProfitMxn: $selected->netProfitMxn,
            futurePositionValueMxn: $selected->futurePositionBonusMxn,
            batchEfficiencyBonusMxn: '0.00',
            expectedDelayCostMxn: '0.00',
            riskPenaltyMxn: '0.00',
            idlePenaltyMxn: '0.00',
            absoluteUtilityMxn: $selected->netProfitMxn,
            expectedMinutes: $selected->totalTimeMin,
            expectedDistanceKm: $selected->totalDistanceKm,
            netHourlyRateMxn: $selected->netHourlyRateMxn,
            risk: $selected->latenessRisk,
            reasonCodes: $selected->reasonCodes,
            routeProvider: 'baseline',
            routingFallback: false,
            candidatesEvaluated: 1,
        );
    }

    private function environmentAt(SimulationRun $run, DateTimeInterface $at): EnvironmentState
    {
        $environment = $this->stateMapper->toEnvironmentState($run);

        return new EnvironmentState(
            simulationTime: \DateTimeImmutable::createFromInterface($at),
            weather: $environment->weather,
            trafficFactor: $environment->trafficFactor,
            surgeZones: $environment->surgeZones,
            roadClosures: $environment->roadClosures,
            closureVersion: $environment->closureVersion,
        );
    }
}
