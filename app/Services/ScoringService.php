<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Coordinates;
use App\DTOs\CourierState;
use App\DTOs\EnvironmentState;
use App\DTOs\EvaluatedOrder;
use App\Enums\Priority;
use App\Enums\ReasonCode;
use App\Models\Order;
use App\Support\Money;
use Carbon\CarbonImmutable;

final class ScoringService
{
    public function __construct(
        private readonly RoutingService $routingService,
        private readonly DemandService $demandService,
    ) {}

    public function evaluate(
        Order $order,
        CourierState $courier,
        EnvironmentState $environment,
    ): EvaluatedOrder {
        $pickupRoute = $this->routingService->route([
            $courier->position,
            new Coordinates((float) $order->restaurant_lat, (float) $order->restaurant_lon),
        ], closureVersion: $environment->closureVersion, trafficBucket: $this->trafficBucket($environment->trafficFactor));
        $deliveryRoute = $this->routingService->route([
            new Coordinates((float) $order->restaurant_lat, (float) $order->restaurant_lon),
            new Coordinates((float) $order->customer_lat, (float) $order->customer_lon),
        ], closureVersion: $environment->closureVersion, trafficBucket: $this->trafficBucket($environment->trafficFactor));

        $traffic = max(0.001, $environment->trafficFactor);
        $weather = $this->weatherFactor($environment->weather);
        $rawTravelTime = $pickupRoute->durationMinutes + $deliveryRoute->durationMinutes;
        $adjustedTravelTime = $rawTravelTime * $traffic * $weather;
        $wait = (float) $order->estimated_restaurant_wait_min;
        $totalTime = $adjustedTravelTime + $wait;
        $totalTime = max(0.000001, $totalTime);
        $totalDistance = $pickupRoute->distanceKm + $deliveryRoute->distanceKm;
        $gross = Money::add($order->base_pay_mxn, $order->surge_bonus_mxn, $order->other_bonus_mxn);
        $operatingCost = Money::multiply((string) $totalDistance, (float) $courier->costPerKmMxn);
        $net = Money::subtract($gross, $operatingCost);
        $hourly = Money::format(((float) $net / $totalTime) * 60);
        $demand = $this->demandService->score($order, $environment->simulationTime);
        $futureBonus = $this->demandService->futurePositionBonus($order, $environment->simulationTime);
        $pickupEfficiency = max(0.0, min(1.0, 1 / (1 + $pickupRoute->distanceKm)));
        $batchCompatibility = max(0.0, min(1.0, $pickupEfficiency * (empty($order->restrictions ?? []) ? 1.0 : 0.75)));
        $batchPotential = max(0.0, min(1.0, $batchCompatibility * ($order->destination_zone === null ? 0.5 : 1.0)));
        $predictedDelivery = CarbonImmutable::instance($environment->simulationTime)->addSeconds((int) round($totalTime * 60));
        $deadline = $order->delivery_deadline ?? $order->pickup_deadline;
        $slack = $deadline === null ? $courier->shiftRemainingMin - $totalTime : ($deadline->getTimestamp() - $predictedDelivery->getTimestamp()) / 60;
        $risk = max(0.0, min(1.0, max(0.0, -$slack) / max(1.0, $totalTime) + max(0.0, $traffic - 1) * 0.25 + max(0.0, $weather - 1) * 0.25));

        $reasons = [];
        $feasible = true;
        if ($deadline !== null && $slack < 0) {
            $feasible = false;
            $reasons[] = ReasonCode::SHIFT_TOO_SHORT;
        }
        if ($totalTime > $courier->shiftRemainingMin + 0.000001) {
            $feasible = false;
            $reasons[] = ReasonCode::SHIFT_TOO_SHORT;
        }
        if (count($courier->currentOrders) >= $courier->maxConcurrentOrders) {
            $feasible = false;
            $reasons[] = ReasonCode::INCOMPATIBLE_BATCH;
        }
        if ($this->hasSafetyRestriction($order, $courier->vehicle)) {
            $feasible = false;
            $reasons[] = ReasonCode::INCOMPATIBLE_BATCH;
        }
        if ($this->hasRoadClosure($order, $environment)) {
            $feasible = false;
            $reasons[] = ReasonCode::ROAD_CLOSURE;
        }
        if ($pickupRoute->isFallback || $deliveryRoute->isFallback) {
            $reasons[] = ReasonCode::TRAFFIC_PENALTY;
        }
        if ($demand >= 0.7) {
            $reasons[] = ReasonCode::HIGH_DEMAND_DESTINATION;
        } elseif ($demand <= 0.3) {
            $reasons[] = ReasonCode::LOW_DEMAND_DESTINATION;
        }
        if ($pickupRoute->distanceKm <= 2) {
            $reasons[] = ReasonCode::SHORT_PICKUP;
        } elseif ($pickupRoute->distanceKm >= 8) {
            $reasons[] = ReasonCode::LONG_PICKUP;
        }
        if ($risk <= 0.25) {
            $reasons[] = ReasonCode::LOW_DELAY_RISK;
        } elseif ($risk >= 0.75) {
            $reasons[] = ReasonCode::HIGH_DELAY_RISK;
        }

        return new EvaluatedOrder(
            orderId: (string) $order->external_id,
            grossPayMxn: $gross,
            deadheadDistanceKm: $pickupRoute->distanceKm,
            deliveryDistanceKm: $deliveryRoute->distanceKm,
            totalDistanceKm: $totalDistance,
            travelTimeMin: $adjustedTravelTime,
            restaurantWaitMin: $wait,
            totalTimeMin: $totalTime,
            operatingCostMxn: $operatingCost,
            netProfitMxn: $net,
            netHourlyRateMxn: $hourly,
            destinationDemandScore: $demand,
            latenessRisk: $risk,
            batchCompatibility: $batchCompatibility,
            score: 0.0,
            priority: Priority::LOW,
            feasible: $feasible,
            reasonCodes: array_values(array_unique($reasons, SORT_REGULAR)),
            futurePositionBonusMxn: $futureBonus,
            batchPotential: $batchPotential,
            slackMin: $slack,
            pickupEfficiency: $pickupEfficiency,
        );
    }

    public function evaluateOrder(Order $order, CourierState $courier, EnvironmentState $environment): EvaluatedOrder
    {
        return $this->evaluate($order, $courier, $environment);
    }

    public function score(Order $order, CourierState $courier, EnvironmentState $environment): EvaluatedOrder
    {
        return $this->evaluate($order, $courier, $environment);
    }

    /** @param iterable<Order> $orders @return list<EvaluatedOrder> */
    public function rank(iterable $orders, CourierState $courier, EnvironmentState $environment): array
    {
        $evaluated = [];
        foreach ($orders as $order) {
            $evaluated[] = $this->evaluate($order, $courier, $environment);
        }

        $metrics = [
            'hourly' => array_map(static fn (EvaluatedOrder $item): float => (float) $item->netHourlyRateMxn, $evaluated),
            'net' => array_map(static fn (EvaluatedOrder $item): float => (float) $item->netProfitMxn, $evaluated),
            'demand' => array_map(static fn (EvaluatedOrder $item): float => $item->destinationDemandScore, $evaluated),
            'batch' => array_map(static fn (EvaluatedOrder $item): float => $item->batchPotential, $evaluated),
            'pickup' => array_map(static fn (EvaluatedOrder $item): float => $item->pickupEfficiency, $evaluated),
            'reliability' => array_map(static fn (EvaluatedOrder $item): float => 1 - $item->latenessRisk, $evaluated),
        ];
        $weights = (array) config('courier.scoring.weights', []);
        $ranked = [];
        foreach ($evaluated as $index => $item) {
            if (! $item->feasible) {
                $ranked[] = $item;

                continue;
            }
            $score = 100 * (
                $this->normalize($metrics['hourly'], $metrics['hourly'][$index]) * (float) ($weights['hourly_profit'] ?? 0)
                + $this->normalize($metrics['net'], $metrics['net'][$index]) * (float) ($weights['net_profit'] ?? 0)
                + $item->destinationDemandScore * (float) ($weights['destination_value'] ?? 0)
                + $item->batchPotential * (float) ($weights['batch_potential'] ?? 0)
                + $item->pickupEfficiency * (float) ($weights['pickup_efficiency'] ?? 0)
                + (1 - $item->latenessRisk) * (float) ($weights['reliability'] ?? 0)
            );
            $score = max(0.0, min(100.0, $score));
            $priority = $this->priority($score);
            $reasons = $item->reasonCodes;
            if ($this->normalize($metrics['hourly'], $metrics['hourly'][$index]) >= 0.75) {
                $reasons[] = ReasonCode::HIGH_HOURLY_RATE;
            }
            if ($this->normalize($metrics['net'], $metrics['net'][$index]) >= 0.75) {
                $reasons[] = ReasonCode::HIGH_NET_PROFIT;
            }
            if ($item->batchPotential >= 0.6) {
                $reasons[] = ReasonCode::BATCH_COMPATIBLE;
            }
            if ($item->slackMin >= (float) config('courier.scoring.safe_slack_min', 10)) {
                $reasons[] = ReasonCode::SHIFT_FIT;
            }
            $ranked[] = new EvaluatedOrder(
                orderId: $item->orderId,
                grossPayMxn: $item->grossPayMxn,
                deadheadDistanceKm: $item->deadheadDistanceKm,
                deliveryDistanceKm: $item->deliveryDistanceKm,
                totalDistanceKm: $item->totalDistanceKm,
                travelTimeMin: $item->travelTimeMin,
                restaurantWaitMin: $item->restaurantWaitMin,
                totalTimeMin: $item->totalTimeMin,
                operatingCostMxn: $item->operatingCostMxn,
                netProfitMxn: $item->netProfitMxn,
                netHourlyRateMxn: $item->netHourlyRateMxn,
                destinationDemandScore: $item->destinationDemandScore,
                latenessRisk: $item->latenessRisk,
                batchCompatibility: $item->batchCompatibility,
                score: $score,
                priority: $priority,
                feasible: true,
                reasonCodes: array_values(array_unique($reasons, SORT_REGULAR)),
                futurePositionBonusMxn: $item->futurePositionBonusMxn,
                batchPotential: $item->batchPotential,
                slackMin: $item->slackMin,
                pickupEfficiency: $item->pickupEfficiency,
            );
        }
        usort($ranked, static fn (EvaluatedOrder $left, EvaluatedOrder $right): int => ($left->feasible <=> $right->feasible) * -1 ?: ($right->score <=> $left->score) ?: strcmp($left->orderId, $right->orderId));

        return array_values($ranked);
    }

    /** @param iterable<Order> $orders @return list<EvaluatedOrder> */
    public function rankOrders(iterable $orders, CourierState $courier, EnvironmentState $environment): array
    {
        return $this->rank($orders, $courier, $environment);
    }

    /** @param list<float> $values */
    private function normalize(array $values, float $value): float
    {
        if ($values === []) {
            return 0.5;
        }
        $min = min($values);
        $max = max($values);
        if (abs($max - $min) < 0.000000001) {
            return 0.5;
        }

        return max(0.0, min(1.0, ($value - $min) / ($max - $min)));
    }

    private function priority(float $score): Priority
    {
        $high = (float) config('courier.scoring.priority_thresholds.high', 80);
        $medium = (float) config('courier.scoring.priority_thresholds.medium', 55);

        return $score >= $high ? Priority::HIGH : ($score >= $medium ? Priority::MEDIUM : Priority::LOW);
    }

    private function weatherFactor(string $weather): float
    {
        return max(0.001, (float) (config('courier.scoring.weather_factors.'.$weather) ?? 1.0));
    }

    private function trafficBucket(float $factor): string
    {
        $bucketSize = max(0.0001, (float) config('courier.routing.traffic_bucket_size', 0.10));

        return 'factor_'.number_format(floor($factor / $bucketSize) * $bucketSize, 2, '.', '');
    }

    private function hasSafetyRestriction(Order $order, string $vehicle): bool
    {
        foreach ((array) $order->restrictions as $restriction) {
            $restriction = strtolower((string) $restriction);
            if (in_array($restriction, ['no_motorcycle', 'motorcycle_forbidden', 'safety_blocked'], true)) {
                return true;
            }
            if (str_starts_with($restriction, 'vehicle_required:') && substr($restriction, 17) !== strtolower($vehicle)) {
                return true;
            }
        }

        return false;
    }

    private function hasRoadClosure(Order $order, EnvironmentState $environment): bool
    {
        foreach ($environment->roadClosures as $closure) {
            if (($closure['closed'] ?? $closure['is_active'] ?? true) === false) {
                continue;
            }
            if (($closure['restaurant_id'] ?? null) === $order->restaurant_id || ($closure['zone'] ?? null) === $order->destination_zone) {
                return true;
            }
        }

        return false;
    }
}
