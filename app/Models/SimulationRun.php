<?php

namespace App\Models;

use App\Enums\ShiftStatus;
use Database\Factories\SimulationRunFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SimulationRun extends Model
{
    /** @use HasFactory<SimulationRunFactory> */
    use HasFactory;

    protected $fillable = [
        'scenario_key',
        'seed',
        'status',
        'simulated_started_at',
        'simulated_current_at',
        'simulated_ends_at',
        'real_started_at',
        'real_finished_at',
        'real_last_tick_at',
        'traffic_factor',
        'weather',
        'closure_version',
        'speed_multiplier',
        'environment_state',
        'config_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShiftStatus::class,
            'seed' => 'integer',
            'simulated_started_at' => 'datetime',
            'simulated_current_at' => 'datetime',
            'simulated_ends_at' => 'datetime',
            'real_started_at' => 'datetime',
            'real_finished_at' => 'datetime',
            'real_last_tick_at' => 'datetime',
            'traffic_factor' => 'decimal:4',
            'closure_version' => 'integer',
            'speed_multiplier' => 'decimal:4',
            'environment_state' => 'array',
            'config_snapshot' => 'array',
        ];
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SimulationEvent::class);
    }

    public function benchmarkResults(): HasMany
    {
        return $this->hasMany(BenchmarkResult::class);
    }

    public function tickRequests(): HasMany
    {
        return $this->hasMany(SimulationTickRequest::class);
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', ShiftStatus::RUNNING->value);
    }
}
