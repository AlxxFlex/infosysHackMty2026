<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class SimulationResource extends JsonResource
{
    public function toArray($request): array
    {
        $this->loadMissing('shifts');

        return ['id' => $this->id, 'scenario_key' => $this->scenario_key, 'seed' => $this->seed, 'status' => $this->status->value, 'simulated_started_at' => $this->simulated_started_at?->toIso8601String(), 'simulated_current_at' => $this->simulated_current_at?->toIso8601String(), 'simulated_ends_at' => $this->simulated_ends_at?->toIso8601String(), 'traffic_factor' => (float) $this->traffic_factor, 'weather' => $this->weather, 'speed_multiplier' => (float) $this->speed_multiplier, 'shifts' => $this->shifts->map(fn ($s) => ['id' => $s->id, 'agent_type' => $s->agent_type->value, 'status' => $s->status->value])->values()->all(), 'is_simulated' => true];
    }
}
