<?php

namespace Tests\Feature;

use App\Services\ExplanationService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExplanationProviderTest extends TestCase
{
    public function test_http_provider_receives_restrictive_facts_prompt_and_valid_output(): void
    {
        config()->set('courier.explanation.provider', 'http');
        config()->set('courier.explanation.endpoint', 'https://llm.test/chat');
        Http::fake(['https://llm.test/chat' => Http::response(['choices' => [['message' => ['content' => 'ORD-001 deja $10.00 MXN.']]]])]);
        $facts = ['selected_order_ids' => ['ORD-001'], 'plan' => ['expected_net_profit_mxn' => '10.00'], 'is_simulated' => true];

        $output = app(ExplanationService::class)->llm($facts);

        $this->assertSame('ORD-001 deja $10.00 MXN.', $output);
        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            return $request->url() === 'https://llm.test/chat'
                && ($body['temperature'] ?? null) === 0
                && str_contains((string) ($body['messages'][0]['content'] ?? ''), 'No elijas pedidos')
                && str_contains((string) ($body['messages'][1]['content'] ?? ''), 'FACTS JSON');
        });
    }

    public function test_provider_errors_and_invalid_json_fall_back_without_throwing(): void
    {
        config()->set('courier.explanation.provider', 'http');
        config()->set('courier.explanation.endpoint', 'https://llm.test/chat');
        $facts = ['selected_order_ids' => ['ORD-001'], 'plan' => ['expected_net_profit_mxn' => '10.00'], 'is_simulated' => true];
        Http::fakeSequence()->pushStatus(500)->push(['choices' => [['message' => ['content' => 'ORD-999 deja $999.00 MXN.']]]]);

        $service = app(ExplanationService::class);

        $this->assertNull($service->llm($facts));
        $this->assertNull($service->llm($facts));
    }
}
