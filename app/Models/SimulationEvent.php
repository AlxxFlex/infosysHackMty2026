<?php

namespace App\Models;

use App\Enums\EventType;
use Database\Factories\SimulationEventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SimulationEvent extends Model
{
    /** @use HasFactory<SimulationEventFactory> */
    use HasFactory;

    protected $fillable = [
        'simulation_run_id',
        'type',
        'scheduled_at',
        'applied_at',
        'sequence',
        'payload',
        'state_before',
        'state_after',
        'reason_codes',
    ];

    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'scheduled_at' => 'datetime',
            'applied_at' => 'datetime',
            'sequence' => 'integer',
            'payload' => 'array',
            'state_before' => 'array',
            'state_after' => 'array',
            'reason_codes' => 'array',
        ];
    }

    public function simulationRun(): BelongsTo
    {
        return $this->belongsTo(SimulationRun::class);
    }

    public function scopePendingAt(Builder $query, \DateTimeInterface $at): Builder
    {
        return $query
            ->whereNull('applied_at')
            ->where('scheduled_at', '<=', $at)
            ->orderBy('scheduled_at')
            ->orderBy('sequence')
            ->orderBy('id');
    }
}
