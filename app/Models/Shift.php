<?php

namespace App\Models;

use App\Enums\AgentType;
use App\Enums\CourierStatus;
use App\Enums\ShiftStatus;
use Database\Factories\ShiftFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    /** @use HasFactory<ShiftFactory> */
    use HasFactory;

    protected $fillable = [
        'simulation_run_id',
        'agent_type',
        'status',
        'courier_status',
        'initial_lat',
        'initial_lon',
        'current_lat',
        'current_lon',
        'max_concurrent_orders',
        'cost_per_km_mxn',
        'gross_earnings_mxn',
        'net_earnings_mxn',
        'operating_cost_mxn',
        'total_distance_km',
        'deadhead_distance_km',
        'active_minutes',
        'idle_minutes',
        'accepted_orders_count',
        'rejected_orders_count',
        'completed_orders_count',
        'late_orders_count',
    ];

    protected function casts(): array
    {
        return [
            'agent_type' => AgentType::class,
            'status' => ShiftStatus::class,
            'courier_status' => CourierStatus::class,
            'initial_lat' => 'decimal:7',
            'initial_lon' => 'decimal:7',
            'current_lat' => 'decimal:7',
            'current_lon' => 'decimal:7',
            'max_concurrent_orders' => 'integer',
            'cost_per_km_mxn' => 'decimal:2',
            'gross_earnings_mxn' => 'decimal:2',
            'net_earnings_mxn' => 'decimal:2',
            'operating_cost_mxn' => 'decimal:2',
            'total_distance_km' => 'decimal:3',
            'deadhead_distance_km' => 'decimal:3',
            'active_minutes' => 'integer',
            'idle_minutes' => 'integer',
            'accepted_orders_count' => 'integer',
            'rejected_orders_count' => 'integer',
            'completed_orders_count' => 'integer',
            'late_orders_count' => 'integer',
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

    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', ShiftStatus::RUNNING->value);
    }
}
