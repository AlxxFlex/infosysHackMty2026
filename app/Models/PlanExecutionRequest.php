<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanExecutionRequest extends Model
{
    protected $fillable = ['shift_id', 'idempotency_key', 'delivery_id'];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }
}
