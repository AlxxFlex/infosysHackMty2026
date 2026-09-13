<?php

namespace Tests\Feature;

use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResultsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");
        if ($connection !== 'mysql' || $database !== 'infoSys_testing') {
            throw new \LogicException('Results tests may only refresh the dedicated MySQL database infoSys_testing.');
        }
    }

    public function test_results_page_is_accessible_and_labels_simulated_values(): void
    {
        $run = app(ShiftService::class)->createRun('demo_normal', 91);

        $this->get(route('results.show', $run))
            ->assertOk()
            ->assertSee('Resultados del simulador controlado')
            ->assertSee('Todos los valores son simulados');
    }
}
