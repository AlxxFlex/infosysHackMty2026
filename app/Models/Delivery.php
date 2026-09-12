<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use Database\Factories\DeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    /** @use HasFactory<DeliveryFactory> */
    use HasFactory;

    protected $fillable = [
        'shift_id',
        'recommendation_id',
        'status',
        'route_sequence',
        'order_ids',
        'simulated_started_at',
        'simulated_finished_at',
        'gross_earnings_mxn',
        'operating_cost_mxn',
        'net_earnings_mxn',
        'total_distance_km',
        'deadhead_distance_km',
        'simulated_duration_minutes',
        'lateness_minutes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'route_sequence' => 'array',
            'order_ids' => 'array',
            'status' => DeliveryStatus::class,
            'simulated_started_at' => 'datetime',
            'simulated_finished_at' => 'datetime',
            'gross_earnings_mxn' => 'decimal:2',
            'operating_cost_mxn' => 'decimal:2',
            'net_earnings_mxn' => 'decimal:2',
            'total_distance_km' => 'decimal:3',
            'deadhead_distance_km' => 'decimal:3',
            'simulated_duration_minutes' => 'decimal:2',
            'lateness_minutes' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(Recommendation::class);
    }
}
