<?php

namespace Tests\Unit;

use App\DTOs\EvaluatedOrder;
use App\DTOs\OptimizedPlan;
use App\Enums\Priority;
use App\Enums\ReasonCode;
use App\Services\ExplanationService;
use App\Services\NoneExplanationProvider;
use PHPUnit\Framework\TestCase;

class ExplanationServiceTest extends TestCase
{
    public function test_facts_and_deterministic_text_include_selected_plan_and_rejected_alternative(): void
    {
        $service = new ExplanationService(new NoneExplanationProvider);
        $facts = $service->buildFacts($this->plan(), [$this->order('ORD-001'), $this->order('ORD-002')], ['config_version' => 'v1']);
        $text = $service->deterministic($facts);

        $this->assertSame('v1', $facts['version']);
        $this->assertSame(['ORD-001'], $facts['selected_order_ids']);
        $this->assertSame('ORD-002', $facts['best_rejected_alternative']['order_id']);
        $this->assertSame('30.00', $facts['best_rejected_alternative']['difference_vs_selected_net_mxn']);
        $this->assertStringContainsString('ORD-002', $text);
        $this->assertStringContainsString('120.00', $text);
    }

    public function test_reason_templates_and_none_provider_are_always_safe(): void
    {
        $service = new ExplanationService(new NoneExplanationProvider);
        foreach (ReasonCode::cases() as $reason) {
            $facts = ['selected_order_ids' => ['ORD-001'], 'plan' => ['feasible' => true, 'expected_net_profit_mxn' => '10.00', 'expected_minutes' => 5, 'net_hourly_rate_mxn' => '120.00', 'reason_codes' => [$reason->value]], 'is_simulated' => true];
            $this->assertNotSame('', $service->deterministic($facts));
            $this->assertNull($service->llm($facts));
        }
    }

    public function test_output_validator_rejects_unknown_ids_and_numbers(): void
    {
        $service = new ExplanationService(new NoneExplanationProvider);
        $facts = ['selected_order_ids' => ['ORD-001'], 'plan' => ['expected_net_profit_mxn' => '10.00'], 'is_simulated' => true];

        $this->assertTrue($service->validateOutput('ORD-001 deja $10.00 MXN.', $facts));
        $this->assertFalse($service->validateOutput('ORD-999 deja $10.00 MXN.', $facts));
        $this->assertFalse($service->validateOutput('ORD-001 deja $999.00 MXN.', $facts));
    }

    /** @return array<string, mixed> */
    private function plan(): OptimizedPlan
    {
        return OptimizedPlan::fromArray([
            'feasible' => true,
            'orders' => ['ORD-001'],
            'sequence' => [['action' => 'PICKUP', 'order_id' => 'ORD-001'], ['action' => 'DROPOFF', 'order_id' => 'ORD-001']],
            'expected_net_profit_mxn' => '120.00',
            'future_position_value_mxn' => '8.00',
            'batch_efficiency_bonus_mxn' => '0.00',
            'expected_delay_cost_mxn' => '2.00',
            'risk_penalty_mxn' => '1.00',
            'idle_penalty_mxn' => '0.00',
            'absolute_utility_mxn' => '125.00',
            'expected_minutes' => 30,
            'expected_distance_km' => 6,
            'net_hourly_rate_mxn' => '240.00',
            'risk' => 0.1,
            'reason_codes' => ['HIGH_NET_PROFIT', 'SHIFT_FIT'],
            'route_provider' => 'fallback',
            'routing_fallback' => true,
            'candidates_evaluated' => 2,
        ]);
    }

    private function order(string $id): EvaluatedOrder
    {
        return EvaluatedOrder::fromArray([
            'order_id' => $id,
            'gross_pay_mxn' => $id === 'ORD-001' ? '130.00' : '100.00',
            'deadhead_distance_km' => 1,
            'delivery_distance_km' => 5,
            'total_distance_km' => 6,
            'travel_time_min' => 25,
            'restaurant_wait_min' => 5,
            'total_time_min' => 30,
            'operating_cost_mxn' => '10.00',
            'net_profit_mxn' => $id === 'ORD-001' ? '120.00' : '90.00',
            'net_hourly_rate_mxn' => $id === 'ORD-001' ? '240.00' : '180.00',
            'destination_demand_score' => 0.8,
            'lateness_risk' => 0.1,
            'batch_compatibility' => 0.8,
            'score' => 80,
            'priority' => Priority::HIGH->value,
            'feasible' => true,
            'reason_codes' => ['HIGH_NET_PROFIT'],
        ]);
    }
}
