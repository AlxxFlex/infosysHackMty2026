<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class TickSimulationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('idempotency_key') && $this->header('Idempotency-Key')) {
            $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
        }
    }

    public function rules(): array
    {
        return ['minutes' => ['required', 'integer', 'min:1', 'max:1440'], 'idempotency_key' => ['required', 'string', 'max:128']];
    }
}
