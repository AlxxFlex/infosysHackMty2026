<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\ShiftOrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftOrder extends Model
{
    /** @use HasFactory<ShiftOrderFactory> */
    use HasFactory;

    protected $fillable = [
        'shift_id',
        'order_id',
        'status',
        'available_at',
        'accepted_at',
        'picked_up_at',
        'delivered_at',
        'rejected_at',
        'expired_at',
        'accepted_metrics',
        'plan_position',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'available_at' => 'datetime',
            'accepted_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
            'rejected_at' => 'datetime',
            'expired_at' => 'datetime',
            'accepted_metrics' => 'array',
            'plan_position' => 'integer',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', OrderStatus::AVAILABLE->value);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', OrderStatus::PENDING->value);
    }
}
