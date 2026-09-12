<?php

namespace App\Models;

use Database\Factories\RouteCacheFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RouteCache extends Model
{
    /** @use HasFactory<RouteCacheFactory> */
    use HasFactory;

    protected $fillable = [
        'cache_key',
        'provider',
        'origin_lat',
        'origin_lon',
        'destination_lat',
        'destination_lon',
        'traffic_bucket',
        'closure_version',
        'distance_km',
        'duration_minutes',
        'geometry_geojson',
        'response_summary',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'origin_lat' => 'decimal:7',
            'origin_lon' => 'decimal:7',
            'destination_lat' => 'decimal:7',
            'destination_lon' => 'decimal:7',
            'closure_version' => 'integer',
            'distance_km' => 'decimal:3',
            'duration_minutes' => 'decimal:2',
            'geometry_geojson' => 'array',
            'response_summary' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
