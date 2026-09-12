<?php

namespace Database\Factories;

use App\Models\DemandZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DemandZone> */
class DemandZoneFactory extends Factory
{
    protected $model = DemandZone::class;

    public function definition(): array
    {
        return [
            'zone_key' => 'CENTRO-'.$this->faker->unique()->numerify('##'),
            'name' => 'Centro',
            'center_lat' => '25.6750000',
            'center_lon' => '-100.3100000',
            'polygon_geojson' => null,
            'demand_by_hour' => ['18' => 0.70, '19' => 0.90, '20' => 0.80],
            'is_active' => true,
        ];
    }
}
