<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['id' => $this->id, 'shift_id' => $this->shift_id, 'recommendation_id' => $this->recommendation_id, 'status' => $this->status->value, 'order_ids' => $this->order_ids, 'route_sequence' => $this->route_sequence, 'metrics' => ['gross_earnings_mxn' => (string) $this->gross_earnings_mxn, 'operating_cost_mxn' => (string) $this->operating_cost_mxn, 'net_earnings_mxn' => (string) $this->net_earnings_mxn, 'total_distance_km' => (float) $this->total_distance_km, 'deadhead_distance_km' => (float) $this->deadhead_distance_km], 'simulated_started_at' => $this->simulated_started_at?->toIso8601String(), 'simulated_finished_at' => $this->simulated_finished_at?->toIso8601String(), 'is_simulated' => true];
    }
}
