<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ExplanationProvider;
use App\DTOs\EvaluatedOrder;
use App\DTOs\OptimizedPlan;
use App\Enums\ReasonCode;
use App\Support\Money;

final class ExplanationService
{
    public function __construct(private readonly ExplanationProvider $provider) {}

    /**
     * Build a JSON-safe, immutable snapshot of the numbers that explain a plan.
     *
     * @param  list<EvaluatedOrder>  $ranking
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function buildFacts(OptimizedPlan $plan, array $ranking, array $context = []): array
    {
        $selected = array_values(array_filter(
            $ranking,
            static fn (EvaluatedOrder $item): bool => in_array($item->orderId, $plan->orderIds, true),
        ));
        $rejected = array_values(array_filter(
            $ranking,
            static fn (EvaluatedOrder $item): bool => ! in_array($item->orderId, $plan->orderIds, true),
        ));
        $bestRejected = $rejected[0] ?? null;
        $operatingCost = '0.00';
        foreach ($selected as $item) {
            $operatingCost = Money::add($operatingCost, $item->operatingCostMxn);
        }
        $alternative = $bestRejected === null ? null : [
            'order_id' => $bestRejected->orderId,
            'net_profit_mxn' => $bestRejected->netProfitMxn,
            'net_hourly_rate_mxn' => $bestRejected->netHourlyRateMxn,
            'total_time_min' => $bestRejected->totalTimeMin,
            'total_distance_km' => $bestRejected->totalDistanceKm,
            'risk' => $bestRejected->latenessRisk,
            'reason_codes' => $this->reasonValues($bestRejected->reasonCodes),
            'difference_vs_selected_net_mxn' => Money::subtract($plan->expectedNetProfitMxn, $bestRejected->netProfitMxn),
            'difference_vs_selected_hourly_mxn' => Money::subtract($plan->netHourlyRateMxn, $bestRejected->netHourlyRateMxn),
        ];

        $facts = [
            'version' => 'v1',
            'selected_order_ids' => $plan->orderIds,
            'selected_orders' => array_map(fn (EvaluatedOrder $item): array => $this->orderFacts($item), $selected),
            'plan' => [
                'feasible' => $plan->feasible,
                'expected_net_profit_mxn' => $plan->expectedNetProfitMxn,
                'operating_cost_mxn' => $operatingCost,
                'expected_minutes' => $plan->expectedMinutes,
                'expected_distance_km' => $plan->expectedDistanceKm,
                'net_hourly_rate_mxn' => $plan->netHourlyRateMxn,
                'deadhead_distance_km' => $plan->deadheadDistanceKm,
                'batch_savings_distance_km' => $plan->batchSavingsDistanceKm,
                'route_overlap' => $plan->routeOverlap,
                'detour_ratio' => $plan->detourRatio,
                'future_position_value_mxn' => $plan->futurePositionValueMxn,
                'batch_efficiency_bonus_mxn' => $plan->batchEfficiencyBonusMxn,
                'expected_delay_cost_mxn' => $plan->expectedDelayCostMxn,
                'risk_penalty_mxn' => $plan->riskPenaltyMxn,
                'idle_penalty_mxn' => $plan->idlePenaltyMxn,
                'absolute_utility_mxn' => $plan->absoluteUtilityMxn,
                'risk' => $plan->risk,
                'reason_codes' => $this->reasonValues($plan->reasonCodes),
                'sequence' => $plan->sequence,
                'route_provider' => $plan->routeProvider,
                'routing_fallback' => $plan->routingFallback,
                'budget_exceeded' => $plan->budgetExceeded,
                'candidates_evaluated' => $plan->candidatesEvaluated,
            ],
            'best_rejected_alternative' => $alternative,
            'context' => [
                'config_version' => $context['config_version'] ?? 'v1',
                'simulation_time' => $context['simulation_time'] ?? null,
                'estimated_demand' => $context['estimated_demand'] ?? null,
                'environment' => $context['environment'] ?? null,
                'end_of_shift' => (bool) ($context['end_of_shift'] ?? false),
            ],
            'is_simulated' => true,
        ];

        // A JSON round-trip prevents callers from mutating DTO references after creation.
        return json_decode(json_encode($facts, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $facts */
    public function deterministic(array $facts): string
    {
        $plan = (array) ($facts['plan'] ?? []);
        $ids = array_values((array) ($facts['selected_order_ids'] ?? []));
        if (($plan['feasible'] ?? false) !== true || $ids === []) {
            return 'No hay un plan factible dentro de las restricciones actuales.';
        }
        $summary = sprintf(
            'Gana el plan de %d pedido(s): deja $%s MXN netos esperados en %.0f min ($%s MXN/h).',
            count($ids),
            (string) ($plan['expected_net_profit_mxn'] ?? '0.00'),
            (float) ($plan['expected_minutes'] ?? 0),
            (string) ($plan['net_hourly_rate_mxn'] ?? '0.00'),
        );
        $reasons = array_values((array) ($plan['reason_codes'] ?? []));
        $primary = $reasons[0] ?? null;
        $explanation = $primary === null ? 'La selección maximiza la utilidad absoluta calculada.' : $this->reasonTemplate($primary);
        $text = $summary.' '.$explanation;
        if (count($ids) > 1) {
            $text .= sprintf(' Es un batch con %.2f km de ahorro y %.0f%% de traslape de ruta.', (float) ($plan['batch_savings_distance_km'] ?? 0), (float) ($plan['route_overlap'] ?? 0) * 100);
        }
        if (($plan['routing_fallback'] ?? false) === true) {
            $text .= ' La ruta usa el fallback determinista de routing.';
        }
        $environment = (array) ($facts['context']['environment'] ?? []);
        if (($environment['surge_zones'] ?? []) !== []) {
            $text .= ' El valor incluye un surge simulado vigente.';
        }
        if (($environment['road_closures'] ?? []) !== []) {
            $text .= ' Se consideraron cierres viales simulados en la ruta.';
        }
        $alternative = $facts['best_rejected_alternative'] ?? null;
        if (is_array($alternative) && isset($alternative['order_id'])) {
            $text .= sprintf(' La mejor alternativa %s deja $%s MXN menos de neto.', $alternative['order_id'], (string) ($alternative['difference_vs_selected_net_mxn'] ?? '0.00'));
        }
        if (($facts['context']['end_of_shift'] ?? false) === true) {
            $text .= ' La decisión prioriza completar antes del final del turno.';
        }

        return $text;
    }

    /** @param array<string, mixed> $facts */
    public function llm(array $facts): ?string
    {
        try {
            $output = $this->provider->explain($facts);
        } catch (\Throwable) {
            return null;
        }

        return is_string($output) && $this->validateOutput($output, $facts) ? trim($output) : null;
    }

    public function providerName(): string
    {
        return $this->provider->name();
    }

    /** @param array<string, mixed> $facts */
    public function validateOutput(string $output, array $facts): bool
    {
        $knownIds = [];
        $knownNumbers = [];
        $this->collectAllowed($facts, $knownIds, $knownNumbers);
        preg_match_all('/\bORD[-_A-Z0-9]+\b/i', $output, $idMatches);
        foreach ($idMatches[0] as $id) {
            if (! in_array(strtoupper($id), array_map('strtoupper', $knownIds), true)) {
                return false;
            }
        }
        $withoutIds = preg_replace('/\bORD[-_A-Z0-9]+\b/i', '', $output) ?? $output;
        preg_match_all('/(?<![A-Za-z])\d+(?:[.,]\d+)?/', $withoutIds, $numberMatches);
        foreach ($numberMatches[0] as $number) {
            if (! in_array($this->normalizeNumber($number), $knownNumbers, true)) {
                return false;
            }
        }

        return trim($output) !== '' && mb_strlen($output) <= 1200;
    }

    /** @param list<ReasonCode|string> $reasons @return list<string> */
    private function reasonValues(array $reasons): array
    {
        return array_values(array_map(static fn (ReasonCode|string $reason): string => $reason instanceof ReasonCode ? $reason->value : $reason, $reasons));
    }

    private function reasonTemplate(string $reason): string
    {
        return match ($reason) {
            ReasonCode::HIGH_HOURLY_RATE->value => 'La razón principal es su mayor tasa neta por hora.',
            ReasonCode::HIGH_NET_PROFIT->value => 'La razón principal es su mayor ganancia neta esperada.',
            ReasonCode::SHORT_PICKUP->value => 'La recogida corta reduce tiempo improductivo.',
            ReasonCode::LOW_DEADHEAD->value => 'La ruta minimiza el traslado sin pedido.',
            ReasonCode::HIGH_DEMAND_DESTINATION->value => 'El destino tiene demanda futura estimada alta.',
            ReasonCode::BATCH_COMPATIBLE->value => 'Los pedidos son compatibles para consolidar la ruta.',
            ReasonCode::LOW_DELAY_RISK->value => 'El riesgo estimado de retraso es bajo.',
            ReasonCode::SURGE_ADVANTAGE->value => 'El surge simulado mejora el valor de la oferta.',
            ReasonCode::SHIFT_FIT->value => 'El plan cabe en el tiempo restante del turno.',
            ReasonCode::LOW_HOURLY_RATE->value => 'Se descartaron opciones con menor tasa neta por hora.',
            ReasonCode::LONG_PICKUP->value => 'La recogida larga reduce la eficiencia de la ruta.',
            ReasonCode::LOW_DEMAND_DESTINATION->value => 'El destino tiene demanda futura estimada baja.',
            ReasonCode::ROAD_CLOSURE->value => 'Se consideró una restricción por cierre vial simulado.',
            ReasonCode::TRAFFIC_PENALTY->value => 'Se penalizó el tiempo por tráfico o fallback de routing.',
            ReasonCode::HIGH_DELAY_RISK->value => 'El riesgo estimado de retraso es alto y queda explícito.',
            ReasonCode::SHIFT_TOO_SHORT->value => 'La restricción de tiempo restante fue respetada.',
            ReasonCode::INCOMPATIBLE_BATCH->value => 'La capacidad o las restricciones impiden combinar más pedidos.',
            default => 'La selección respeta las restricciones calculadas del turno.',
        };
    }

    /** @return array<string, mixed> */
    private function orderFacts(EvaluatedOrder $item): array
    {
        return [
            'order_id' => $item->orderId,
            'gross_pay_mxn' => $item->grossPayMxn,
            'operating_cost_mxn' => $item->operatingCostMxn,
            'net_profit_mxn' => $item->netProfitMxn,
            'net_hourly_rate_mxn' => $item->netHourlyRateMxn,
            'deadhead_distance_km' => $item->deadheadDistanceKm,
            'total_distance_km' => $item->totalDistanceKm,
            'total_time_min' => $item->totalTimeMin,
            'restaurant_wait_min' => $item->restaurantWaitMin,
            'destination_demand_score' => $item->destinationDemandScore,
            'future_position_bonus_mxn' => $item->futurePositionBonusMxn,
            'risk' => $item->latenessRisk,
            'reason_codes' => $this->reasonValues($item->reasonCodes),
            'feasible' => $item->feasible,
        ];
    }

    /** @param mixed $value @param list<string> $ids @param list<string> $numbers */
    private function collectAllowed(mixed $value, array &$ids, array &$numbers): void
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $this->collectAllowed($item, $ids, $numbers);
            }
        } elseif (is_string($value)) {
            if (preg_match('/^ORD[-_A-Z0-9]+$/i', $value)) {
                $ids[] = $value;
            }
            if (is_numeric($value)) {
                $numbers[] = $this->normalizeNumber($value);
            }
        } elseif (is_int($value) || is_float($value)) {
            $numbers[] = $this->normalizeNumber((string) $value);
        }
        $ids = array_values(array_unique($ids));
        $numbers = array_values(array_unique($numbers));
    }

    private function normalizeNumber(string $value): string
    {
        $value = str_replace(',', '.', trim($value));
        if (! is_numeric($value)) {
            return $value;
        }

        return rtrim(rtrim(number_format((float) $value, 6, '.', ''), '0'), '.') ?: '0';
    }
}
