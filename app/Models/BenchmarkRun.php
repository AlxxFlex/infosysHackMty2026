<?php

namespace App\Models;

use App\Enums\BenchmarkStatus;
use Database\Factories\BenchmarkRunFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BenchmarkRun extends Model
{
    /** @use HasFactory<BenchmarkRunFactory> */
    use HasFactory;

    protected $fillable = [
        'scenario_key',
        'seeds',
        'config_snapshot',
        'config_version',
        'algorithm_version',
        'status',
        'total_seeds',
        'completed_seeds',
        'failed_seeds',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'seeds' => 'array',
            'config_snapshot' => 'array',
            'status' => BenchmarkStatus::class,
            'total_seeds' => 'integer',
            'completed_seeds' => 'integer',
            'failed_seeds' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function results(): HasMany
    {
        return $this->hasMany(BenchmarkResult::class);
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', BenchmarkStatus::RUNNING->value);
    }
}
