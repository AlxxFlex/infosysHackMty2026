<?php

namespace App\Models;

use Database\Factories\DemandZoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DemandZone extends Model
{
    /** @use HasFactory<DemandZoneFactory> */
    use HasFactory;

    protected $fillable = [
        'zone_key',
        'name',
        'center_lat',
        'center_lon',
        'polygon_geojson',
        'demand_by_hour',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'center_lat' => 'decimal:7',
            'center_lon' => 'decimal:7',
            'polygon_geojson' => 'array',
            'demand_by_hour' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
