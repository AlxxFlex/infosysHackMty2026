<?php

namespace Tests\Feature;

use App\Enums\AgentType;
use App\Enums\BenchmarkStatus;
use App\Services\BenchmarkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BenchmarkServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");
        if ($connection !== 'mysql' || $database !== 'infoSys_testing') {
            throw new \LogicException('Benchmark tests may only refresh the dedicated MySQL database infoSys_testing.');
        }
    }

    public function test_benchmark_runs_same_seed_for_both_agents_and_persists_realized_metrics(): void
    {
        config()->set('courier.routing.provider', 'fallback');
        config()->set('courier.benchmark.tick_minutes', 10);
        Cache::flush();

        $service = app(BenchmarkService::class);
        $benchmark = $service->create('demo_normal', [7]);
        $completed = $service->run($benchmark);
        $summary = $service->summary($completed);

        $this->assertSame(BenchmarkStatus::COMPLETED, $completed->status);
        $this->assertSame(1, $completed->completed_seeds);
        $this->assertSame(0, $completed->failed_seeds);
        $this->assertCount(2, $completed->results);
        $this->assertSame([AgentType::BASELINE->value, AgentType::COURIER_AI->value], $completed->results->pluck('agent_type')->map(fn ($agent): string => $agent->value)->sort()->values()->all());
        $this->assertSame(1, count($summary['comparisons']));
        $this->assertArrayHasKey('net_earnings_mxn', $summary['agents'][AgentType::COURIER_AI->value]['mean']);
        $this->assertTrue($summary['is_simulated']);
        $again = $service->resume($completed);
        $this->assertCount(2, $again->results);
        $this->assertSame($summary['comparisons'], $service->summary($again)['comparisons']);
    }

    public function test_improvement_handles_positive_negative_and_zero_baseline(): void
    {
        $service = app(BenchmarkService::class);

        $this->assertSame(25.0, $service->improvementPercent('125.00', '100.00'));
        $this->assertSame(-25.0, $service->improvementPercent('75.00', '100.00'));
        $this->assertNull($service->improvementPercent('10.00', '0.00'));
    }
}
