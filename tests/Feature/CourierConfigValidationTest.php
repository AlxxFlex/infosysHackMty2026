<?php

namespace Tests\Feature;

use App\Support\CourierConfigValidator;
use Tests\TestCase;

class CourierConfigValidationTest extends TestCase
{
    public function test_default_configuration_is_valid_and_optional_services_can_be_disabled(): void
    {
        $validator = app(CourierConfigValidator::class);

        $this->assertSame([], $validator->errors());
    }

    public function test_invalid_runtime_values_return_actionable_messages(): void
    {
        $original = [
            'timeout' => config('courier.routing.timeout_seconds'),
            'provider' => config('courier.explanation.provider'),
            'endpoint' => config('courier.explanation.endpoint'),
            'weights' => config('courier.scoring.weights'),
        ];

        config()->set('courier.routing.timeout_seconds', 0);
        config()->set('courier.explanation.provider', 'http');
        config()->set('courier.explanation.endpoint', '');
        config()->set('courier.scoring.weights', ['hourly_profit' => 2]);

        $errors = app(CourierConfigValidator::class)->errors();

        $this->assertContains('courier.routing.timeout_seconds debe ser un número mayor que cero.', $errors);
        $this->assertContains('courier.explanation.endpoint es obligatorio cuando LLM_PROVIDER no es none; o desactiva el proveedor.', $errors);
        $this->assertContains('courier.scoring.weights debe sumar 1.0 para conservar el score reproducible.', $errors);

        config()->set('courier.routing.timeout_seconds', $original['timeout']);
        config()->set('courier.explanation.provider', $original['provider']);
        config()->set('courier.explanation.endpoint', $original['endpoint']);
        config()->set('courier.scoring.weights', $original['weights']);
    }
}
