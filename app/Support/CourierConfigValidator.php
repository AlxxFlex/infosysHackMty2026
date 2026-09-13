<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * Validates the small set of runtime settings that can make a demo unsafe or
 * non-reproducible. It deliberately does not require optional providers.
 */
final class CourierConfigValidator
{
    /** @return list<string> */
    public function errors(?ConfigRepository $config = null): array
    {
        $config ??= app('config');
        $errors = [];

        $positiveIntegers = [
            'courier.routing.timeout_seconds',
            'courier.routing.cache_ttl_seconds',
            'courier.routing.fallback_speed_kmh',
            'courier.optimization.time_budget_ms',
            'courier.explanation.timeout_seconds',
            'courier.explanation.max_tokens',
            'courier.benchmark.tick_minutes',
        ];
        foreach ($positiveIntegers as $key) {
            $value = $config->get($key);
            if (! is_numeric($value) || (float) $value <= 0) {
                $errors[] = "{$key} debe ser un número mayor que cero.";
            }
        }

        foreach (['courier.simulation.speed_multiplier', 'courier.routing.road_factor'] as $key) {
            $value = $config->get($key);
            if (! is_numeric($value) || (float) $value <= 0) {
                $errors[] = "{$key} debe ser un número mayor que cero.";
            }
        }
        $costPerKm = $config->get('courier.simulation.cost_per_km_mxn');
        if (! is_numeric($costPerKm) || (float) $costPerKm < 0) {
            $errors[] = 'courier.simulation.cost_per_km_mxn debe ser un número no negativo.';
        }

        foreach (['courier.routing.retries', 'courier.routing.retry_sleep_ms', 'courier.explanation.retries', 'courier.explanation.retry_sleep_ms', 'courier.optimization.max_candidates', 'courier.optimization.max_candidate_set_size'] as $key) {
            $value = $config->get($key);
            if (! is_numeric($value) || (int) $value < 0) {
                $errors[] = "{$key} debe ser un entero no negativo.";
            }
        }

        $routingProvider = strtolower(trim((string) $config->get('courier.routing.provider', 'osrm')));
        if (! in_array($routingProvider, ['osrm', 'fallback'], true)) {
            $errors[] = 'courier.routing.provider debe ser osrm o fallback.';
        }
        if ($routingProvider === 'osrm') {
            $baseUrl = trim((string) $config->get('courier.routing.base_url', ''));
            if (filter_var($baseUrl, FILTER_VALIDATE_URL) === false || ! in_array(strtolower((string) parse_url($baseUrl, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                $errors[] = 'courier.routing.base_url debe ser una URL http(s) válida; usa fallback si OSRM no estará disponible.';
            }
        }

        $llmProvider = strtolower(trim((string) $config->get('courier.explanation.provider', 'none')));
        if ($llmProvider === '') {
            $errors[] = 'courier.explanation.provider no puede estar vacío; usa none para el modo determinista.';
        }
        if ($llmProvider !== 'none' && trim((string) $config->get('courier.explanation.endpoint', '')) === '') {
            $errors[] = 'courier.explanation.endpoint es obligatorio cuando LLM_PROVIDER no es none; o desactiva el proveedor.';
        }

        $broadcast = strtolower(trim((string) $config->get('broadcasting.default', 'null')));
        $broadcast = $broadcast === '' ? 'null' : $broadcast;
        if (! in_array($broadcast, ['reverb', 'pusher', 'ably', 'redis', 'log', 'null'], true)) {
            $errors[] = 'BROADCAST_CONNECTION debe ser uno de reverb, pusher, ably, redis, log o null.';
        }

        $weights = (array) $config->get('courier.scoring.weights', []);
        $weightTotal = array_sum(array_map(static fn (mixed $weight): float => is_numeric($weight) ? (float) $weight : 0.0, $weights));
        if ($weights === [] || abs($weightTotal - 1.0) > 0.001) {
            $errors[] = 'courier.scoring.weights debe sumar 1.0 para conservar el score reproducible.';
        }

        $maxOrders = $config->get('courier.simulation.max_concurrent_orders');
        if (! is_numeric($maxOrders) || (int) $maxOrders < 1 || (int) $maxOrders > 10) {
            $errors[] = 'courier.simulation.max_concurrent_orders debe estar entre 1 y 10.';
        }

        if (app()->environment('production') && (bool) $config->get('app.debug', false)) {
            $errors[] = 'APP_DEBUG debe ser false en producción o en una demo pública.';
        }

        return array_values(array_unique($errors));
    }

    public function assertValid(?ConfigRepository $config = null): void
    {
        $errors = $this->errors($config);
        if ($errors !== []) {
            throw new \RuntimeException("Configuración Courier AI inválida:\n- ".implode("\n- ", $errors));
        }
    }
}
