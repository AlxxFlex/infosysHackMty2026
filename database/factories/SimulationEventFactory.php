<?php

namespace Database\Factories;

use App\Enums\EventType;
use App\Models\SimulationEvent;
use App\Models\SimulationRun;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<SimulationEvent> */
class SimulationEventFactory extends Factory
{
    protected $model = SimulationEvent::class;

    public function definition(): array
    {
        return [
            'simulation_run_id' => SimulationRun::factory(),
            'type' => EventType::TRAFFIC_CHANGED,
            'scheduled_at' => Carbon::parse('2026-09-12 18:10:00'),
            'applied_at' => null,
            'sequence' => 0,
            'payload' => ['traffic_factor' => 1.2, 'is_simulated' => true],
            'state_before' => null,
            'state_after' => null,
            'reason_codes' => null,
        ];
    }
}
