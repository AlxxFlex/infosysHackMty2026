<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ExplanationProvider;
use Illuminate\Support\Facades\Http;

final class HttpExplanationProvider implements ExplanationProvider
{
    public function explain(array $facts): ?string
    {
        $endpoint = trim((string) config('courier.explanation.endpoint', ''));
        if ($endpoint === '') {
            return null;
        }

        $request = Http::timeout(max(1, (int) config('courier.explanation.timeout_seconds', 5)))
            ->acceptJson()
            ->asJson();
        $retries = max(0, (int) config('courier.explanation.retries', 1));
        if ($retries > 0) {
            $request = $request->retry($retries, max(0, (int) config('courier.explanation.retry_sleep_ms', 100)), throw: false);
        }
        $key = trim((string) config('courier.explanation.api_key', ''));
        if ($key !== '') {
            $request = $request->withToken($key);
        }

        $response = $request->post($endpoint, [
            'model' => (string) config('courier.explanation.model', 'gpt-4o-mini'),
            'temperature' => 0,
            'max_tokens' => max(32, (int) config('courier.explanation.max_tokens', 180)),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Explica la decisión ya calculada. No elijas pedidos, no cambies el plan y usa exclusivamente cifras e IDs presentes en FACTS. Responde sólo con texto breve en español.',
                ],
                [
                    'role' => 'user',
                    'content' => "FACTS JSON:\n".json_encode($facts, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                ],
            ],
        ]);

        if (! $response->successful()) {
            return null;
        }

        $content = $response->json('choices.0.message.content')
            ?? $response->json('explanation');

        return is_string($content) && trim($content) !== '' ? trim($content) : null;
    }

    public function name(): string
    {
        return (string) config('courier.explanation.provider', 'http');
    }
}
