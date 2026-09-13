<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class ShiftResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['id' => $this->id, 'simulation_run_id' => $this->simulation_run_id, 'agent_type' => $this->agent_type->value, 'status' => $this->status->value, 'courier_status' => $this->courier_status->value, 'position' => ['lat' => (float) $this->current_lat, 'lon' => (float) $this->current_lon], 'metrics' => ['gross_earnings_mxn' => (string) $this->gross_earnings_mxn, 'net_earnings_mxn' => (string) $this->net_earnings_mxn, 'total_distance_km' => (float) $this->total_distance_km, 'completed_orders_count' => (int) $this->completed_orders_count], 'is_simulated' => true];
    }
}
