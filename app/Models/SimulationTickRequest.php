<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SimulationTickRequest extends Model
{
    protected $fillable = [
        'simulation_run_id',
        'idempotency_key',
        'requested_minutes',
        'simulated_from',
        'simulated_to',
    ];

    protected function casts(): array
    {
        return [
            'requested_minutes' => 'integer',
            'simulated_from' => 'datetime',
            'simulated_to' => 'datetime',
        ];
    }

    public function simulationRun(): BelongsTo
    {
        return $this->belongsTo(SimulationRun::class);
    }
}
