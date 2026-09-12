<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'simulation_run_id',
        'external_id',
        'scenario_order_key',
        'spawn_time',
        'expires_at',
        'pickup_deadline',
        'delivery_deadline',
        'restaurant_id',
        'restaurant_name',
        'restaurant_lat',
        'restaurant_lon',
        'customer_lat',
        'customer_lon',
        'destination_zone',
        'base_pay_mxn',
        'surge_bonus_mxn',
        'other_bonus_mxn',
        'estimated_restaurant_wait_min',
        'restrictions',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'spawn_time' => 'datetime',
            'expires_at' => 'datetime',
            'pickup_deadline' => 'datetime',
            'delivery_deadline' => 'datetime',
            'restaurant_lat' => 'decimal:7',
            'restaurant_lon' => 'decimal:7',
            'customer_lat' => 'decimal:7',
            'customer_lon' => 'decimal:7',
            'base_pay_mxn' => 'decimal:2',
            'surge_bonus_mxn' => 'decimal:2',
            'other_bonus_mxn' => 'decimal:2',
            'estimated_restaurant_wait_min' => 'integer',
            'restrictions' => 'array',
            'metadata' => 'array',
        ];
    }

    public function simulationRun(): BelongsTo
    {
        return $this->belongsTo(SimulationRun::class);
    }

    public function shiftOrders(): HasMany
    {
        return $this->hasMany(ShiftOrder::class);
    }
}
