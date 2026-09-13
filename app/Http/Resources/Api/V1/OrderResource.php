<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['id' => $this->id, 'external_id' => $this->external_id, 'scenario_order_key' => $this->scenario_order_key, 'restaurant' => ['id' => $this->restaurant_id, 'name' => $this->restaurant_name, 'lat' => (float) $this->restaurant_lat, 'lon' => (float) $this->restaurant_lon], 'customer' => ['lat' => (float) $this->customer_lat, 'lon' => (float) $this->customer_lon, 'zone' => $this->destination_zone], 'spawn_time' => $this->spawn_time?->toIso8601String(), 'expires_at' => $this->expires_at?->toIso8601String(), 'delivery_deadline' => $this->delivery_deadline?->toIso8601String(), 'pay' => ['base_mxn' => (string) $this->base_pay_mxn, 'surge_mxn' => (string) $this->surge_bonus_mxn, 'other_bonus_mxn' => (string) $this->other_bonus_mxn], 'is_simulated' => true];
    }
}
