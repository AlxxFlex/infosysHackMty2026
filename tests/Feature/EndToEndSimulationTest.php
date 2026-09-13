<?php

namespace Tests\Feature;

use App\Enums\AgentType;
use App\Enums\OrderStatus;
use App\Livewire\CourierDashboard;
use App\Models\SimulationRun;
use App\Services\BenchmarkService;
use App\Services\PlanExecutionService;
use App\Services\RecommendationService;
use App\Services\ShiftService;
use App\Services\SimulatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class EndToEndSimulationTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");
        if ($connection !== 'mysql' || $database !== 'infoSys_testing') {
            throw new \LogicException('E2E tests may only refresh the dedicated MySQL database infoSys_testing.');
        }
    }

    public function test_complete_domain_flow_shares_environment_and_preserves_realized_invariants(): void
    {
        config()->set('courier.routing.provider', 'fallback');
        Cache::flush();
        $shiftService = app(ShiftService::class);
        $simulator = app(SimulatorService::class);
        $recommendations = app(RecommendationService::class);
        $execution = app(PlanExecutionService::class);

        $run = $shiftService->startRun($shiftService->createRun('demo_normal', 1801));
        $run = $simulator->tick($run, 1, 'e2e-publish');
        $this->assertGreaterThanOrEqual(5, $run->orders()->count());
        $this->assertSame(2, $run->shifts()->count());

        foreach ($run->fresh('shifts')->shifts as $shift) {
            $result = $recommendations->recommend($shift, $run);
            $this->assertTrue($result['plan']->feasible);
            $this->assertNotEmpty($result['plan']->orderIds);
            $execution->acceptAndExecute($shift, $run, $result['plan'], $result['recommendation'], 'e2e-'.$shift->agent_type->value);
        }
        $run = $simulator->tick($run, 39, 'e2e-traffic');
        $this->assertSame('1.4000', (string) $run->fresh()->traffic_factor);
        $this->assertNotNull($run->fresh()->events()->whereNotNull('applied_at')->first());

        $run = $simulator->tick($run, 20, 'e2e-surge');
        $this->assertNotEmpty($run->fresh()->environment_state['surge_zones']);
        $finished = $shiftService->finishRun($run);
        $this->assertSame('FINISHED', $finished->status->value);
        $this->assertSame(['FINISHED', 'FINISHED'], $finished->shifts()->pluck('status')->map(fn ($status): string => $status->value)->all());

        foreach ($finished->fresh('shifts')->shifts as $shift) {
            $gross = (float) $shift->gross_earnings_mxn;
            $cost = (float) $shift->operating_cost_mxn;
            $this->assertSame(round($gross - $cost, 2), round((float) $shift->net_earnings_mxn, 2));
            $this->assertGreaterThanOrEqual(0, (float) $shift->total_distance_km);
            $this->assertGreaterThanOrEqual(0, (int) $shift->active_minutes);
            $this->assertSame(0, $shift->shiftOrders()->where('status', OrderStatus::DELIVERED->value)->whereNull('delivered_at')->count());
            foreach ($shift->deliveries as $delivery) {
                $pickupPositions = collect($delivery->route_sequence)->filter(fn ($stop): bool => ($stop['action'] ?? null) === 'PICKUP')->mapWithKeys(fn ($stop, $position): array => [$stop['order_id'] => $position]);
                foreach (collect($delivery->route_sequence)->filter(fn ($stop): bool => ($stop['action'] ?? null) === 'DROPOFF') as $position => $stop) {
                    $this->assertLessThan($position, $pickupPositions[$stop['order_id']] ?? -1);
                }
            }
        }
        $summary = app(BenchmarkService::class)->simulationSummary($finished);
        $this->assertArrayHasKey('improvement_percent', $summary);
        $this->assertTrue($summary['is_simulated']);
    }

    public function test_api_and_livewire_use_same_deterministic_plan_and_polling_survives_realtime_off(): void
    {
        config()->set('courier.routing.provider', 'fallback');
        Cache::flush();
        $first = app(ShiftService::class)->startRun(app(ShiftService::class)->createRun('demo_normal', 1802));
        $first = app(SimulatorService::class)->tick($first, 1, 'e2e-parity-api');
        $firstShift = $first->shifts()->where('agent_type', AgentType::COURIER_AI->value)->firstOrFail();
        $firstPlan = app(RecommendationService::class)->recommend($firstShift, $first)['plan']->toArray();

        $created = $this->postJson('/api/v1/simulations', ['scenario_key' => 'demo_normal', 'seed' => 1802])->assertCreated();
        $secondId = $created->json('data.id');
        $this->postJson("/api/v1/simulations/{$secondId}/start")->assertOk();
        $this->withHeader('Idempotency-Key', 'e2e-parity-api')->postJson("/api/v1/simulations/{$secondId}/tick", ['minutes' => 1])->assertOk();
        $secondShiftId = SimulationRun::findOrFail($secondId)->shifts()->where('agent_type', AgentType::COURIER_AI->value)->value('id');
        $apiPlan = $this->postJson("/api/v1/shifts/{$secondShiftId}/recommendations")->assertCreated()->json('data.plan');
        $this->assertSame($firstPlan['order_ids'], $apiPlan['order_ids']);
        $this->assertSame($firstPlan['sequence'], $apiPlan['sequence']);

        Livewire::test(CourierDashboard::class)
            ->set('seed', 1802)
            ->call('startShift')
            ->call('manualTick')
            ->call('generateRecommendation')
            ->assertSet('runId', fn ($value): bool => $value !== null)
            ->assertSet('recommendation.plan.order_ids', $firstPlan['order_ids']);
        $this->get('/courier')->assertOk()->assertSee('wire:poll.1s="poll"', false);
    }
}
