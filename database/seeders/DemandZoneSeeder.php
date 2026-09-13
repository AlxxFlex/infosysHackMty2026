<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DemandZone;
use Illuminate\Database\Seeder;

final class DemandZoneSeeder extends Seeder
{
    public function run(): void
    {
        $zones = [
            ['zone_key' => 'CENTRO', 'name' => 'Centro', 'center_lat' => '25.6750000', 'center_lon' => '-100.3100000', 'demand_by_hour' => ['18' => 0.70, '19' => 0.90, '20' => 0.80]],
            ['zone_key' => 'TEC', 'name' => 'Tecnológico', 'center_lat' => '25.6515000', 'center_lon' => '-100.2900000', 'demand_by_hour' => ['18' => 0.80, '19' => 0.95, '20' => 0.85]],
            ['zone_key' => 'SAN_PEDRO', 'name' => 'San Pedro', 'center_lat' => '25.6600000', 'center_lon' => '-100.3600000', 'demand_by_hour' => ['18' => 0.60, '19' => 0.75, '20' => 0.70]],
        ];

        foreach ($zones as $zone) {
            DemandZone::query()->updateOrCreate(
                ['zone_key' => $zone['zone_key']],
                [...$zone, 'polygon_geojson' => null, 'is_active' => true],
            );
        }
    }
}
