<?php

namespace App\Models;

use App\Enums\RecommendationStatus;
use Database\Factories\RecommendationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recommendation extends Model
{
    /** @use HasFactory<RecommendationFactory> */
    use HasFactory;

    protected $fillable = [
        'shift_id',
        'simulation_time',
        'config_version',
        'ranking',
        'selected_order_ids',
        'route_sequence',
        'metrics',
        'reason_codes',
        'visual_score',
        'absolute_utility',
        'expected_net_profit_mxn',
        'expected_minutes',
        'expected_distance_km',
        'expected_hourly_rate_mxn',
        'estimated_risk',
        'status',
        'deterministic_explanation',
        'llm_explanation',
        'routing_duration_ms',
        'optimization_duration_ms',
        'candidates_evaluated',
    ];

    protected function casts(): array
    {
        return [
            'simulation_time' => 'datetime',
            'status' => RecommendationStatus::class,
            'ranking' => 'array',
            'selected_order_ids' => 'array',
            'route_sequence' => 'array',
            'metrics' => 'array',
            'reason_codes' => 'array',
            'visual_score' => 'decimal:2',
            'absolute_utility' => 'decimal:2',
            'expected_net_profit_mxn' => 'decimal:2',
            'expected_minutes' => 'decimal:2',
            'expected_distance_km' => 'decimal:3',
            'expected_hourly_rate_mxn' => 'decimal:2',
            'estimated_risk' => 'decimal:6',
            'routing_duration_ms' => 'integer',
            'optimization_duration_ms' => 'integer',
            'candidates_evaluated' => 'integer',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', RecommendationStatus::PENDING->value);
    }
}
