<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\Priority;
use App\Enums\ReasonCode;
use InvalidArgumentException;

final readonly class EvaluatedOrder
{
    /**
     * @param  list<ReasonCode>  $reasonCodes
     */
    public function __construct(
        public string $orderId,
        public string $grossPayMxn,
        public float $deadheadDistanceKm,
        public float $deliveryDistanceKm,
        public float $totalDistanceKm,
        public float $travelTimeMin,
        public float $restaurantWaitMin,
        public float $totalTimeMin,
        public string $operatingCostMxn,
        public string $netProfitMxn,
        public string $netHourlyRateMxn,
        public float $destinationDemandScore,
        public float $latenessRisk,
        public float $batchCompatibility,
        public float $score,
        public Priority $priority,
        public bool $feasible,
        public array $reasonCodes = [],
        public string $futurePositionBonusMxn = '0.00',
        public float $batchPotential = 0.0,
    ) {
        if ($this->orderId === '') {
            throw new InvalidArgumentException('Evaluated order requires an id.');
        }

        foreach ([
            'deadhead distance' => $this->deadheadDistanceKm,
            'delivery distance' => $this->deliveryDistanceKm,
            'total distance' => $this->totalDistanceKm,
            'travel time' => $this->travelTimeMin,
            'restaurant wait' => $this->restaurantWaitMin,
        ] as $label => $number) {
            if (! is_finite($number) || $number < 0) {
                throw new InvalidArgumentException("{$label} must be finite and non-negative.");
            }
        }

        if ($this->totalDistanceKm + 0.000001 < $this->deadheadDistanceKm + $this->deliveryDistanceKm) {
            throw new InvalidArgumentException('Total distance cannot be less than its route segments.');
        }

        if ($this->totalTimeMin + 0.000001 < $this->travelTimeMin + $this->restaurantWaitMin) {
            throw new InvalidArgumentException('Total time cannot be less than travel and restaurant wait time.');
        }

        if (! is_finite($this->totalTimeMin) || $this->totalTimeMin <= 0) {
            throw new InvalidArgumentException('Total time must be greater than zero.');
        }

        foreach ([
            'destination demand' => $this->destinationDemandScore,
            'lateness risk' => $this->latenessRisk,
            'batch compatibility' => $this->batchCompatibility,
            'batch potential' => $this->batchPotential,
        ] as $label => $number) {
            if (! is_finite($number) || $number < 0 || $number > 1) {
                throw new InvalidArgumentException("{$label} must be between 0 and 1.");
            }
        }

        if (! is_finite($this->score) || $this->score < 0 || $this->score > 100) {
            throw new InvalidArgumentException('Score must be between 0 and 100.');
        }

        self::assertDecimal($this->grossPayMxn, 'Gross pay');
        self::assertDecimal($this->operatingCostMxn, 'Operating cost');
        self::assertDecimal($this->netProfitMxn, 'Net profit');
        self::assertDecimal($this->netHourlyRateMxn, 'Net hourly rate');
        self::assertDecimal($this->futurePositionBonusMxn, 'Future position bonus');

        if ((float) $this->operatingCostMxn >= 0 && (float) $this->netProfitMxn > (float) $this->grossPayMxn + 0.000001) {
            throw new InvalidArgumentException('Net profit cannot exceed gross pay when operating cost is non-negative.');
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
            orderId: (string) ($value['order_id'] ?? ''),
            grossPayMxn: (string) ($value['gross_pay_mxn'] ?? '0.00'),
            deadheadDistanceKm: (float) ($value['deadhead_distance_km'] ?? 0),
            deliveryDistanceKm: (float) ($value['delivery_distance_km'] ?? 0),
            totalDistanceKm: (float) ($value['total_distance_km'] ?? 0),
            travelTimeMin: (float) ($value['travel_time_min'] ?? 0),
            restaurantWaitMin: (float) ($value['restaurant_wait_min'] ?? 0),
            totalTimeMin: (float) ($value['total_time_min'] ?? 0),
            operatingCostMxn: (string) ($value['operating_cost_mxn'] ?? '0.00'),
            netProfitMxn: (string) ($value['net_profit_mxn'] ?? '0.00'),
            netHourlyRateMxn: (string) ($value['net_hourly_rate_mxn'] ?? '0.00'),
            destinationDemandScore: (float) ($value['destination_demand_score'] ?? 0),
            latenessRisk: (float) ($value['lateness_risk'] ?? 0),
            batchCompatibility: (float) ($value['batch_compatibility'] ?? 0),
            score: (float) ($value['score'] ?? 0),
            priority: ($value['priority'] ?? null) instanceof Priority
                ? $value['priority']
                : Priority::from((string) ($value['priority'] ?? 'LOW')),
            feasible: (bool) ($value['feasible'] ?? false),
            reasonCodes: $reasons,
            futurePositionBonusMxn: (string) ($value['future_position_bonus_mxn'] ?? '0.00'),
            batchPotential: (float) ($value['batch_potential'] ?? 0),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'order_id' => $this->orderId,
            'gross_pay_mxn' => $this->grossPayMxn,
            'deadhead_distance_km' => $this->deadheadDistanceKm,
            'delivery_distance_km' => $this->deliveryDistanceKm,
            'total_distance_km' => $this->totalDistanceKm,
            'travel_time_min' => $this->travelTimeMin,
            'restaurant_wait_min' => $this->restaurantWaitMin,
            'total_time_min' => $this->totalTimeMin,
            'operating_cost_mxn' => $this->operatingCostMxn,
            'net_profit_mxn' => $this->netProfitMxn,
            'net_hourly_rate_mxn' => $this->netHourlyRateMxn,
            'destination_demand_score' => $this->destinationDemandScore,
            'lateness_risk' => $this->latenessRisk,
            'batch_compatibility' => $this->batchCompatibility,
            'score' => $this->score,
            'priority' => $this->priority->value,
            'feasible' => $this->feasible,
            'reason_codes' => array_map(static fn (ReasonCode $reason): string => $reason->value, $this->reasonCodes),
            'future_position_bonus_mxn' => $this->futurePositionBonusMxn,
            'batch_potential' => $this->batchPotential,
        ];
    }

    private static function assertDecimal(string $value, string $label): void
    {
        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException("{$label} must be a decimal string.");
        }
    }
}
