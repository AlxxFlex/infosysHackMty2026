<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class SimulationEventResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'simulation_run_id' => $this->simulation_run_id,
            'type' => $this->type->value,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'applied_at' => $this->applied_at?->toIso8601String(),
            'sequence' => (int) $this->sequence,
            'payload' => $this->payload,
            'state_before' => $this->state_before,
            'state_after' => $this->state_after,
            'reason_codes' => $this->reason_codes ?? [],
            'is_simulated' => true,
        ];
    }
}
