<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CreateSimulationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['scenario_key' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_-]+$/'], 'seed' => ['required', 'integer', 'min:0'], 'simulated_started_at' => ['nullable', 'date'], 'config_snapshot' => ['nullable', 'array', 'max:128']];
    }
}
