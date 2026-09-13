<?php

namespace Tests\Feature;

use App\Enums\AgentType;
use App\Models\SimulationRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiSimulationTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");
        if ($connection !== 'mysql' || $database !== 'infoSys_testing') {
            throw new \LogicException('API tests may only refresh the dedicated MySQL database infoSys_testing.');
        }
    }

    public function test_versioned_api_runs_simulation_and_returns_stable_envelopes(): void
    {
        $created = $this->postJson('/api/v1/simulations', ['scenario_key' => 'demo_normal', 'seed' => 42]);
        $created->assertCreated()->assertJsonPath('meta.is_simulated', true)->assertJsonMissingPath('data.config_snapshot');
        $runId = $created->json('data.id');

        $this->postJson("/api/v1/simulations/{$runId}/start")->assertOk();
        $tick = $this->withHeader('Idempotency-Key', 'api-tick-1')->postJson("/api/v1/simulations/{$runId}/tick", ['minutes' => 1]);
        $tick->assertOk()->assertJsonPath('meta.idempotency_key', 'api-tick-1');
        $this->withHeader('Idempotency-Key', 'api-tick-1')->postJson("/api/v1/simulations/{$runId}/tick", ['minutes' => 1])
            ->assertJsonPath('data.simulated_current_at', $tick->json('data.simulated_current_at'));

        $shiftId = SimulationRun::findOrFail($runId)->shifts()->where('agent_type', AgentType::COURIER_AI->value)->value('id');
        $this->getJson("/api/v1/shifts/{$shiftId}/orders")->assertOk()->assertJsonPath('meta.is_simulated', true);
        $recommendation = $this->postJson("/api/v1/shifts/{$shiftId}/recommendations");
        $recommendation->assertCreated()->assertJsonStructure(['data' => ['id', 'ranking', 'plan'], 'meta']);
        $recommendationId = $recommendation->json('data.id');
        $accepted = $this->withHeader('Idempotency-Key', 'api-accept-1')->postJson("/api/v1/shifts/{$shiftId}/plans/accept", ['recommendation_id' => $recommendationId]);
        $accepted->assertCreated();
        $this->withHeader('Idempotency-Key', 'api-accept-1')->postJson("/api/v1/shifts/{$shiftId}/plans/accept", ['recommendation_id' => $recommendationId])
            ->assertJsonPath('data.id', $accepted->json('data.id'));
    }

    public function test_api_validation_and_conflicts_use_documented_codes(): void
    {
        $this->postJson('/api/v1/simulations', [])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
        $this->getJson('/api/v1/simulations/999999')->assertNotFound();
    }

    public function test_local_api_can_schedule_a_simulated_event(): void
    {
        $run = $this->postJson('/api/v1/simulations', ['scenario_key' => 'demo_normal', 'seed' => 43])->json('data.id');
        $this->postJson("/api/v1/simulations/{$run}/events", [
            'type' => 'TRAFFIC_CHANGED',
            'scheduled_minute' => 3,
            'sequence' => 1,
            'payload' => ['traffic_factor' => 1.3, 'is_simulated' => true],
        ])->assertCreated()->assertJsonPath('data.type', 'TRAFFIC_CHANGED')->assertJsonPath('data.is_simulated', true);
    }
}
