<?php

namespace App\Models;

use App\Enums\AgentType;
use App\Enums\BenchmarkStatus;
use Database\Factories\BenchmarkResultFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BenchmarkResult extends Model
{
    /** @use HasFactory<BenchmarkResultFactory> */
    use HasFactory;

    protected $fillable = [
        'benchmark_run_id',
        'simulation_run_id',
        'seed',
        'agent_type',
        'status',
        'gross_earnings_mxn',
        'operating_cost_mxn',
        'net_earnings_mxn',
        'net_hourly_rate_mxn',
        'total_distance_km',
        'deadhead_distance_km',
        'active_minutes',
        'idle_minutes',
        'accepted_orders_count',
        'rejected_orders_count',
        'completed_orders_count',
        'late_orders_count',
        'productive_time_percent',
        'metrics',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'agent_type' => AgentType::class,
            'status' => BenchmarkStatus::class,
            'seed' => 'integer',
            'gross_earnings_mxn' => 'decimal:2',
            'operating_cost_mxn' => 'decimal:2',
            'net_earnings_mxn' => 'decimal:2',
            'net_hourly_rate_mxn' => 'decimal:2',
            'total_distance_km' => 'decimal:3',
            'deadhead_distance_km' => 'decimal:3',
            'active_minutes' => 'integer',
            'idle_minutes' => 'integer',
            'accepted_orders_count' => 'integer',
            'rejected_orders_count' => 'integer',
            'completed_orders_count' => 'integer',
            'late_orders_count' => 'integer',
            'productive_time_percent' => 'decimal:2',
            'metrics' => 'array',
        ];
    }

    public function benchmarkRun(): BelongsTo
    {
        return $this->belongsTo(BenchmarkRun::class);
    }

    public function simulationRun(): BelongsTo
    {
        return $this->belongsTo(SimulationRun::class);
    }
}
