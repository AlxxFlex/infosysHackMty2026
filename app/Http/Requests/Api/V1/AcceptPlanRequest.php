<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class AcceptPlanRequest extends FormRequest
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
        return ['idempotency_key' => ['required', 'string', 'max:128'], 'recommendation_id' => ['nullable', 'integer', 'exists:recommendations,id'], 'plan' => ['nullable', 'array', 'max:64'], 'order_ids' => ['nullable', 'array', 'min:1', 'max:2'], 'order_ids.*' => ['string', 'max:100'], 'sequence' => ['nullable', 'array', 'max:8'], 'sequence.*' => ['array', 'max:4']];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            if (! $this->filled('recommendation_id') && ! $this->filled('plan') && ! $this->filled('order_ids')) {
                $v->errors()->add('plan', 'Provide recommendation_id, plan, or order_ids.');
            }
        });
    }
}
