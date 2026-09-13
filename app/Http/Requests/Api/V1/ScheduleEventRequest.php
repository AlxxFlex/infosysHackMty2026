<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\EventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduleEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(EventType::class)],
            'scheduled_minute' => ['nullable', 'integer', 'min:0', 'max:1440', 'required_without:scheduled_at'],
            'scheduled_at' => ['nullable', 'date', 'required_without:scheduled_minute'],
            'sequence' => ['nullable', 'integer', 'min:0'],
            'payload' => ['required', 'array', 'max:32'],
            'payload.is_simulated' => ['nullable', 'boolean', 'accepted'],
        ];
    }
}
