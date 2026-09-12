<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Shift;
use App\Models\ShiftOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ShiftOrder> */
class ShiftOrderFactory extends Factory
{
    protected $model = ShiftOrder::class;

    public function definition(): array
    {
        return [
            'shift_id' => Shift::factory(),
            'order_id' => Order::factory(),
            'status' => OrderStatus::PENDING,
            'available_at' => null,
            'accepted_at' => null,
            'picked_up_at' => null,
            'delivered_at' => null,
            'rejected_at' => null,
            'expired_at' => null,
            'accepted_metrics' => null,
            'plan_position' => null,
        ];
    }
}
