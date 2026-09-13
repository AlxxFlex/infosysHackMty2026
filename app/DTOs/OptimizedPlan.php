<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\ReasonCode;
use InvalidArgumentException;

final readonly class OptimizedPlan
{
    /**
     * @param  list<string>  $orderIds
     * @param  list<string|array<string, mixed>>  $sequence
     * @param  list<ReasonCode>  $reasonCodes
     */
    public function __construct(
        public bool $feasible,
        public array $orderIds,
        public array $sequence,
        public string $expectedNetProfitMxn,
        public string $futurePositionValueMxn,
        public string $batchEfficiencyBonusMxn,
        public string $expectedDelayCostMxn,
        public string $riskPenaltyMxn,
        public string $idlePenaltyMxn,
        public string $absoluteUtilityMxn,
        public float $expectedMinutes,
        public float $expectedDistanceKm,
        public string $netHourlyRateMxn,
        public float $risk,
        public array $reasonCodes = [],
        public string $routeProvider = 'unknown',
        public bool $routingFallback = false,
        public bool $budgetExceeded = false,
        public int $candidatesEvaluated = 0,
        public float $batchSavingsDistanceKm = 0.0,
        public float $detourRatio = 0.0,
        public float $routeOverlap = 0.0,
        public float $deadheadDistanceKm = 0.0,
    ) {
        foreach ($this->orderIds as $orderId) {
            if (! is_string($orderId) || $orderId === '') {
                throw new InvalidArgumentException('Plan order ids must be non-empty strings.');
            }
        }

        foreach ($this->sequence as $stop) {
            if (! is_string($stop) && ! is_array($stop)) {
                throw new InvalidArgumentException('Plan sequence entries must be strings or structured stops.');
            }
        }

        if (! is_finite($this->expectedMinutes) || $this->expectedMinutes < 0) {
            throw new InvalidArgumentException('Expected plan minutes cannot be negative.');
        }

        if (! is_finite($this->expectedDistanceKm) || $this->expectedDistanceKm < 0) {
            throw new InvalidArgumentException('Expected plan distance cannot be negative.');
        }

        if (! is_finite($this->risk) || $this->risk < 0 || $this->risk > 1) {
            throw new InvalidArgumentException('Plan risk must be between 0 and 1.');
        }

        if ($this->routeProvider === '' || $this->candidatesEvaluated < 0) {
            throw new InvalidArgumentException('Plan provider and candidate count are invalid.');
        }

        foreach (['batch savings distance' => $this->batchSavingsDistanceKm, 'detour ratio' => $this->detourRatio, 'route overlap' => $this->routeOverlap, 'deadhead distance' => $this->deadheadDistanceKm] as $label => $number) {
            if (! is_finite($number) || $number < 0 || ($label === 'route overlap' && $number > 1)) {
                throw new InvalidArgumentException("{$label} must be non-negative and route overlap must be between 0 and 1.");
            }
        }

        foreach ([
            'expected net profit' => $this->expectedNetProfitMxn,
            'future position value' => $this->futurePositionValueMxn,
            'batch efficiency bonus' => $this->batchEfficiencyBonusMxn,
            'expected delay cost' => $this->expectedDelayCostMxn,
            'risk penalty' => $this->riskPenaltyMxn,
            'idle penalty' => $this->idlePenaltyMxn,
            'absolute utility' => $this->absoluteUtilityMxn,
            'net hourly rate' => $this->netHourlyRateMxn,
        ] as $label => $value) {
            if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
                throw new InvalidArgumentException("{$label} must be a decimal string.");
            }
        }

        foreach ($this->reasonCodes as $reasonCode) {
            if (! $reasonCode instanceof ReasonCode) {
                throw new InvalidArgumentException('Reason codes must use the ReasonCode enum.');
            }
        }
    }

    /** @param array<string, mixed> $value */
    public static function fromArray(array $value): self
    {
        $reasons = array_map(
            static fn (ReasonCode|string $reason): ReasonCode => $reason instanceof ReasonCode ? $reason : ReasonCode::from($reason),
            array_values((array) ($value['reason_codes'] ?? [])),
        );

        return new self(
            feasible: (bool) ($value['feasible'] ?? false),
            orderIds: array_values((array) ($value['order_ids'] ?? $value['orders'] ?? [])),
            sequence: array_values((array) ($value['sequence'] ?? [])),
            expectedNetProfitMxn: (string) ($value['expected_net_profit_mxn'] ?? '0.00'),
            futurePositionValueMxn: (string) ($value['future_position_value_mxn'] ?? '0.00'),
            batchEfficiencyBonusMxn: (string) ($value['batch_efficiency_bonus_mxn'] ?? '0.00'),
            expectedDelayCostMxn: (string) ($value['expected_delay_cost_mxn'] ?? '0.00'),
            riskPenaltyMxn: (string) ($value['risk_penalty_mxn'] ?? '0.00'),
            idlePenaltyMxn: (string) ($value['idle_penalty_mxn'] ?? '0.00'),
            absoluteUtilityMxn: (string) ($value['absolute_utility_mxn'] ?? $value['absolute_utility'] ?? '0.00'),
            expectedMinutes: (float) ($value['expected_minutes'] ?? 0),
            expectedDistanceKm: (float) ($value['expected_distance_km'] ?? 0),
            netHourlyRateMxn: (string) ($value['net_hourly_rate_mxn'] ?? '0.00'),
            risk: (float) ($value['risk'] ?? 0),
            reasonCodes: $reasons,
            routeProvider: (string) ($value['route_provider'] ?? 'unknown'),
            routingFallback: (bool) ($value['routing_fallback'] ?? false),
            budgetExceeded: (bool) ($value['budget_exceeded'] ?? false),
            candidatesEvaluated: (int) ($value['candidates_evaluated'] ?? 0),
            batchSavingsDistanceKm: (float) ($value['batch_savings_distance_km'] ?? 0),
            detourRatio: (float) ($value['detour_ratio'] ?? 0),
            routeOverlap: (float) ($value['route_overlap'] ?? 0),
            deadheadDistanceKm: (float) ($value['deadhead_distance_km'] ?? 0),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'feasible' => $this->feasible,
            'order_ids' => $this->orderIds,
            'sequence' => $this->sequence,
            'expected_net_profit_mxn' => $this->expectedNetProfitMxn,
            'future_position_value_mxn' => $this->futurePositionValueMxn,
            'batch_efficiency_bonus_mxn' => $this->batchEfficiencyBonusMxn,
            'expected_delay_cost_mxn' => $this->expectedDelayCostMxn,
            'risk_penalty_mxn' => $this->riskPenaltyMxn,
            'idle_penalty_mxn' => $this->idlePenaltyMxn,
            'absolute_utility_mxn' => $this->absoluteUtilityMxn,
            'expected_minutes' => $this->expectedMinutes,
            'expected_distance_km' => $this->expectedDistanceKm,
            'net_hourly_rate_mxn' => $this->netHourlyRateMxn,
            'risk' => $this->risk,
            'reason_codes' => array_map(static fn (ReasonCode $reason): string => $reason->value, $this->reasonCodes),
            'route_provider' => $this->routeProvider,
            'routing_fallback' => $this->routingFallback,
            'budget_exceeded' => $this->budgetExceeded,
            'candidates_evaluated' => $this->candidatesEvaluated,
            'batch_savings_distance_km' => $this->batchSavingsDistanceKm,
            'detour_ratio' => $this->detourRatio,
            'route_overlap' => $this->routeOverlap,
            'deadhead_distance_km' => $this->deadheadDistanceKm,
        ];
    }
}
