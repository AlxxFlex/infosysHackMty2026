<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class RecommendationResource extends JsonResource
{
    public function toArray($request): array
    {
        $facts = $this->metrics['explanation_facts'] ?? [
            'version' => 'v1',
            'selected_order_ids' => $this->selected_order_ids,
            'is_simulated' => true,
        ];

        return ['id' => $this->id, 'shift_id' => $this->shift_id, 'simulation_time' => $this->simulation_time?->toIso8601String(), 'status' => $this->status->value, 'ranking' => $this->ranking, 'selected_order_ids' => $this->selected_order_ids, 'sequence' => $this->route_sequence, 'plan' => $this->metrics, 'facts' => $facts, 'explanation' => ['source' => $this->llm_explanation === null ? 'deterministic' : 'llm', 'deterministic' => $this->deterministic_explanation, 'llm' => $this->llm_explanation], 'is_simulated' => true];
    }
}
