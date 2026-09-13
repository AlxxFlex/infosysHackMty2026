<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\EvaluatedOrder;
use App\DTOs\OptimizedPlan;
use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\RecommendationStatus;
use App\Events\SimulationUpdated;
use App\Exceptions\InvalidPlanExecution;
use App\Models\Delivery;
use App\Models\PlanExecutionRequest;
use App\Models\Recommendation;
use App\Models\Shift;
use App\Models\ShiftOrder;
use App\Models\SimulationRun;
use App\Support\Money;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

final class PlanExecutionService
{
    public function acceptAndExecute(
        Shift|int $shift,
        SimulationRun|int $run,
        OptimizedPlan|EvaluatedOrder $plan,
        ?Recommendation $recommendation = null,
        ?string $idempotencyKey = null,
    ): Delivery {
        return DB::transaction(function () use ($shift, $run, $plan, $recommendation, $idempotencyKey): Delivery {
            $runId = $run instanceof SimulationRun ? $run->id : $run;
            $lockedRun = SimulationRun::query()->lockForUpdate()->findOrFail($runId);
            $shiftId = $shift instanceof Shift ? $shift->id : $shift;
            $lockedShift = Shift::query()->lockForUpdate()->findOrFail($shiftId);
            if ((int) $lockedShift->simulation_run_id !== (int) $lockedRun->id) {
                throw new InvalidPlanExecution('Shift does not belong to the simulation run.');
            }
            if ($idempotencyKey !== null) {
                $idempotencyKey = trim($idempotencyKey);
                if ($idempotencyKey === '' || strlen($idempotencyKey) > 128) {
                    throw new InvalidPlanExecution('Idempotency key is invalid.');
                }
                $existing = PlanExecutionRequest::query()->where('shift_id', $lockedShift->id)
                    ->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($existing?->delivery_id) {
                    return Delivery::query()->findOrFail($existing->delivery_id);
                }
            }

            $orderIds = $plan instanceof OptimizedPlan ? $plan->orderIds : [$plan->orderId];
            if ($plan instanceof OptimizedPlan && ! $plan->feasible) {
                throw new InvalidPlanExecution('Cannot execute an infeasible plan.');
            }
            if ($orderIds === [] || count($orderIds) !== count(array_unique($orderIds))) {
                throw new InvalidPlanExecution('Execution requires one or more unique order ids.');
            }
            $shiftOrders = ShiftOrder::query()
                ->where('shift_id', $lockedShift->id)
                ->whereHas('order', fn ($query) => $query->whereIn('external_id', $orderIds))
                ->with('order')
                ->lockForUpdate()
                ->get();
            if ($shiftOrders->count() !== count($orderIds)) {
                throw new InvalidPlanExecution('One or more selected orders do not belong to the shift.');
            }
            foreach ($shiftOrders as $shiftOrder) {
                if ($shiftOrder->status !== OrderStatus::AVAILABLE || $shiftOrder->order->expires_at->lessThanOrEqualTo($lockedRun->simulated_current_at)) {
                    throw new InvalidPlanExecution('Selected order is no longer available.');
                }
            }
            if ($lockedShift->deliveries()->where('status', DeliveryStatus::IN_PROGRESS->value)->exists()) {
                throw new InvalidPlanExecution('Shift already has an in-progress delivery.');
            }

            $metrics = $this->metrics($plan, $shiftOrders);
            $sequence = $plan instanceof OptimizedPlan
                ? $plan->sequence
                : [['action' => 'PICKUP', 'order_id' => $plan->orderId], ['action' => 'DROPOFF', 'order_id' => $plan->orderId]];
            $recommendation ??= $this->createDecisionRecommendation($lockedShift, $lockedRun, $plan, $sequence);
            if ($recommendation->status !== RecommendationStatus::ACCEPTED) {
                $recommendation->forceFill(['status' => RecommendationStatus::ACCEPTED])->save();
            }
            $startedAt = CarbonImmutable::instance($lockedRun->simulated_current_at);
            $duration = max(0.01, (float) ($plan instanceof OptimizedPlan ? $plan->expectedMinutes : $plan->totalTimeMin));
            $shiftOrders->each(fn (ShiftOrder $item) => $item->forceFill([
                'status' => OrderStatus::ACCEPTED,
                'accepted_at' => $startedAt,
                'accepted_metrics' => $plan instanceof OptimizedPlan ? $plan->toArray() : $plan->toArray(),
            ])->save());

            $delivery = $lockedShift->deliveries()->create([
                'recommendation_id' => $recommendation->id,
                'status' => DeliveryStatus::IN_PROGRESS,
                'route_sequence' => $sequence,
                'order_ids' => $orderIds,
                'simulated_started_at' => $startedAt,
                'gross_earnings_mxn' => $metrics['gross'],
                'operating_cost_mxn' => $metrics['cost'],
                'net_earnings_mxn' => $metrics['net'],
                'total_distance_km' => $metrics['distance'],
                'deadhead_distance_km' => $metrics['deadhead'],
                'simulated_duration_minutes' => $duration,
                'metadata' => [
                    'is_simulated' => true,
                    'agent_type' => $lockedShift->agent_type->value,
                    'expected_finish_at' => $startedAt->addMinutes($duration)->toIso8601String(),
                ],
            ]);
            if ($idempotencyKey !== null) {
                PlanExecutionRequest::query()->create([
                    'shift_id' => $lockedShift->id,
                    'idempotency_key' => $idempotencyKey,
                    'delivery_id' => $delivery->id,
                ]);
            }

            return $delivery;
        });
    }

    public function advance(SimulationRun|int $run, DateTimeInterface $at): void
    {
        DB::transaction(function () use ($run, $at): void {
            $runId = $run instanceof SimulationRun ? $run->id : $run;
            $lockedRun = SimulationRun::query()->lockForUpdate()->findOrFail($runId);
            $target = CarbonImmutable::createFromInterface($at);
            Delivery::query()
                ->whereIn('status', [DeliveryStatus::IN_PROGRESS->value])
                ->whereHas('shift', fn ($query) => $query->where('simulation_run_id', $lockedRun->id))
                ->with('shift')
                ->lockForUpdate()
                ->get()
                ->each(function (Delivery $delivery) use ($target, $lockedRun): void {
                    $started = CarbonImmutable::instance($delivery->simulated_started_at);
                    $duration = max(0.01, (float) $delivery->simulated_duration_minutes);
                    $elapsed = max(0.0, ($target->getTimestamp() - $started->getTimestamp()) / 60);
                    $shiftOrders = ShiftOrder::query()
                        ->where('shift_id', $delivery->shift_id)
                        ->whereHas('order', fn ($query) => $query->whereIn('external_id', $delivery->order_ids))
                        ->with('order')
                        ->lockForUpdate()
                        ->get();
                    if ($elapsed < $duration) {
                        $stage = $elapsed >= $duration * 0.4 ? OrderStatus::PICKED_UP : OrderStatus::PICKING_UP;
                        $shiftOrders->each(fn (ShiftOrder $item) => $item->forceFill(['status' => $stage])->save());

                        return;
                    }
                    $finishedAt = $started->addMinutes($duration);
                    $shiftOrders->each(fn (ShiftOrder $item) => $item->forceFill([
                        'status' => OrderStatus::DELIVERED,
                        'picked_up_at' => $started->addMinutes($duration * 0.4),
                        'delivered_at' => $finishedAt,
                    ])->save());
                    $orderIds = array_values(array_filter((array) $delivery->order_ids));
                    $late = 0;
                    foreach ($shiftOrders as $item) {
                        if ($item->order?->delivery_deadline !== null) {
                            $late = max($late, max(0, (int) ceil(($finishedAt->getTimestamp() - $item->order->delivery_deadline->getTimestamp()) / 60)));
                        }
                    }
                    $delivery->forceFill([
                        'status' => DeliveryStatus::COMPLETED,
                        'simulated_finished_at' => $finishedAt,
                        'lateness_minutes' => $late,
                    ])->save();
                    $shift = $delivery->shift->fresh();
                    $lastOrder = $shiftOrders->last()?->order;
                    $shift->forceFill([
                        'gross_earnings_mxn' => Money::add((string) $shift->gross_earnings_mxn, (string) $delivery->gross_earnings_mxn),
                        'operating_cost_mxn' => Money::add((string) $shift->operating_cost_mxn, (string) $delivery->operating_cost_mxn),
                        'net_earnings_mxn' => Money::add((string) $shift->net_earnings_mxn, (string) $delivery->net_earnings_mxn),
                        'total_distance_km' => (float) $shift->total_distance_km + (float) $delivery->total_distance_km,
                        'deadhead_distance_km' => (float) $shift->deadhead_distance_km + (float) $delivery->deadhead_distance_km,
                        'completed_orders_count' => (int) $shift->completed_orders_count + count($orderIds),
                        'late_orders_count' => (int) $shift->late_orders_count + ($late > 0 ? count($orderIds) : 0),
                        'current_lat' => $lastOrder?->customer_lat ?? $shift->current_lat,
                        'current_lon' => $lastOrder?->customer_lon ?? $shift->current_lon,
                    ])->save();
                    DB::afterCommit(fn () => SimulationUpdated::dispatch($lockedRun->id, 'delivery.completed', $finishedAt->toIso8601String(), [(string) $delivery->id, (string) $shift->id], ['status' => DeliveryStatus::COMPLETED->value]));
                });
        });
    }

    /** @return array{gross: string, cost: string, net: string, distance: float, deadhead: float} */
    private function metrics(OptimizedPlan|EvaluatedOrder $plan, $shiftOrders): array
    {
        if ($plan instanceof OptimizedPlan) {
            $gross = '0.00';
            foreach ($shiftOrders as $shiftOrder) {
                $gross = Money::add($gross, (string) $shiftOrder->order->base_pay_mxn, (string) $shiftOrder->order->surge_bonus_mxn, (string) $shiftOrder->order->other_bonus_mxn);
            }
            $cost = Money::multiply((string) $plan->expectedDistanceKm, (float) $shiftOrders->first()->shift->cost_per_km_mxn);

            return [
                'gross' => $gross,
                'cost' => $cost,
                'net' => Money::subtract($gross, $cost),
                'distance' => $plan->expectedDistanceKm,
                'deadhead' => $plan->deadheadDistanceKm,
            ];
        }

        return [
            'gross' => $plan->grossPayMxn,
            'cost' => $plan->operatingCostMxn,
            'net' => $plan->netProfitMxn,
            'distance' => $plan->totalDistanceKm,
            'deadhead' => $plan->deadheadDistanceKm,
        ];
    }

    private function createDecisionRecommendation(Shift $shift, SimulationRun $run, OptimizedPlan|EvaluatedOrder $plan, array $sequence): Recommendation
    {
        $selected = $plan instanceof OptimizedPlan ? $plan->toArray() : $plan->toArray();

        return $shift->recommendations()->create([
            'simulation_time' => $run->simulated_current_at,
            'config_version' => 'baseline-v1',
            'ranking' => [$selected],
            'selected_order_ids' => $plan instanceof OptimizedPlan ? $plan->orderIds : [$plan->orderId],
            'route_sequence' => $sequence,
            'metrics' => $selected + ['is_simulated' => true],
            'reason_codes' => array_map(static fn ($reason): string => $reason->value, $plan->reasonCodes),
            'visual_score' => $plan instanceof OptimizedPlan ? null : $plan->score,
            'absolute_utility' => $plan instanceof OptimizedPlan ? $plan->absoluteUtilityMxn : $plan->netProfitMxn,
            'expected_net_profit_mxn' => $plan instanceof OptimizedPlan ? $plan->expectedNetProfitMxn : $plan->netProfitMxn,
            'expected_minutes' => $plan instanceof OptimizedPlan ? $plan->expectedMinutes : $plan->totalTimeMin,
            'expected_distance_km' => $plan instanceof OptimizedPlan ? $plan->expectedDistanceKm : $plan->totalDistanceKm,
            'expected_hourly_rate_mxn' => $plan instanceof OptimizedPlan ? $plan->netHourlyRateMxn : $plan->netHourlyRateMxn,
            'estimated_risk' => $plan instanceof OptimizedPlan ? $plan->risk : $plan->latenessRisk,
            'status' => RecommendationStatus::PENDING,
            'deterministic_explanation' => 'Greedy Highest Gross Pay: primer pedido factible por pago bruto, tiempo y clave estable.',
            'routing_duration_ms' => 0,
            'optimization_duration_ms' => 0,
            'candidates_evaluated' => 1,
        ]);
    }
}
