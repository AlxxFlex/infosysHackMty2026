<?php

namespace Tests\Feature;

use App\Enums\EventType;
use App\Livewire\DemoControl;
use App\Models\SimulationRun;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DemoControlTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");
        if ($connection !== 'mysql' || $database !== 'infoSys_testing') {
            throw new \LogicException('Demo tests may only refresh the dedicated MySQL database infoSys_testing.');
        }
    }

    public function test_controlled_demo_starts_with_six_offers_and_event_buttons_are_idempotent(): void
    {
        Livewire::test(DemoControl::class)
            ->call('start')
            ->call('start')
            ->assertSet('status', 'RUNNING')
            ->assertSet('offersCount', 6)
            ->call('setSpeed', 5)
            ->assertSet('speed', 5)
            ->call('triggerSurge')
            ->call('triggerSurge')
            ->call('triggerClosure')
            ->assertSet('error', '');

        $run = SimulationRun::query()->latest('id')->firstOrFail();
        $this->assertSame(1, SimulationRun::query()->count());
        $this->assertSame(1, $run->events()->where('type', EventType::SURGE_STARTED->value)->count());
        $this->assertSame(1, $run->events()->where('type', EventType::ROAD_CLOSED->value)->count());
    }

    public function test_reset_requires_confirmation_and_only_deletes_selected_run(): void
    {
        $run = app(ShiftService::class)->createRun('demo_normal', 1902);
        Livewire::test(DemoControl::class)
            ->set('runId', $run->id)
            ->set('status', 'IDLE')
            ->call('requestReset')
            ->assertSet('confirmReset', true)
            ->call('resetRun')
            ->assertSet('runId', 0)
            ->assertSet('confirmReset', false);

        $this->assertDatabaseMissing('simulation_runs', ['id' => $run->id]);
    }

    public function test_demo_route_is_available_in_local_testing_and_exposes_visible_script(): void
    {
        $this->get('/demo/control')->assertOk()->assertSee('Panel de demo controlada')->assertSee('Guion visible');
    }

    public function test_ten_consecutive_demo_cycles_can_tick_react_finish_and_reset_without_crashing(): void
    {
        for ($cycle = 1; $cycle <= 10; $cycle++) {
            $component = Livewire::test(DemoControl::class)
                ->set('seed', 9000 + $cycle)
                ->call('start')
                ->assertSet('status', 'RUNNING')
                ->assertSet('offersCount', 6)
                ->call('tick')
                ->assertSet('error', '')
                ->call('triggerSurge')
                ->call('tick')
                ->call('finish')
                ->assertSet('status', 'FINISHED')
                ->call('requestReset')
                ->call('resetRun')
                ->assertSet('runId', 0)
                ->assertSet('error', '');

            $this->assertSame(0, SimulationRun::query()->count(), "El ciclo {$cycle} dejó runs sin limpiar.");
            unset($component);
        }
    }
}
