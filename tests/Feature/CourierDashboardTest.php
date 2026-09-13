<?php

namespace Tests\Feature;

use App\Livewire\CourierDashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CourierDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");
        if ($connection !== 'mysql' || $database !== 'infoSys_testing') {
            throw new \LogicException('Dashboard tests may only refresh the dedicated MySQL database infoSys_testing.');
        }
    }

    public function test_courier_entrypoint_uses_livewire_and_declares_simulation(): void
    {
        $this->get('/courier')->assertOk()->assertSee('Courier AI')->assertSee('ESCENARIO SIMULADO')->assertSee('wire:id');
    }

    public function test_livewire_runs_tick_and_recommendation_through_services(): void
    {
        Livewire::test(CourierDashboard::class)
            ->call('startShift')
            ->assertSet('runId', fn ($value): bool => $value !== null)
            ->assertSet('status', 'RUNNING')
            ->call('manualTick')
            ->assertSet('simulatedCurrentAt', fn ($value): bool => $value !== null)
            ->call('generateRecommendation')
            ->assertSet('recommendation', fn ($value): bool => is_array($value) && array_key_exists('plan', $value));
    }
}
