<?php

namespace Tests\Feature;

use App\Enums\AgentType;
use App\Enums\BenchmarkStatus;
use App\Enums\CourierStatus;
use App\Enums\DeliveryStatus;
use App\Enums\EventType;
use App\Enums\OrderStatus;
use App\Enums\RecommendationStatus;
use App\Enums\ShiftStatus;
use App\Models\BenchmarkResult;
use App\Models\BenchmarkRun;
use App\Models\Delivery;
use App\Models\DemandZone;
use App\Models\Order;
use App\Models\Recommendation;
use App\Models\RouteCache;
use App\Models\Shift;
use App\Models\ShiftOrder;
use App\Models\SimulationEvent;
use App\Models\SimulationRun;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DomainModelsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($connection !== 'mysql' || $database !== 'infoSys_testing') {
            throw new \LogicException(
                'Domain model tests may only refresh the dedicated MySQL database infoSys_testing.'
            );
        }
    }

    public function test_models_have_bidirectional_relationships_and_typed_casts(): void
    {
        $run = SimulationRun::factory()->create([
            'status' => ShiftStatus::RUNNING,
            'environment_state' => ['traffic_factor' => 1.2],
        ]);
        $courier = Shift::factory()->for($run, 'simulationRun')->create([
            'agent_type' => AgentType::COURIER_AI,
            'status' => ShiftStatus::RUNNING,
            'courier_status' => CourierStatus::AVAILABLE,
        ]);
        $baseline = Shift::factory()->for($run, 'simulationRun')->create([
            'agent_type' => AgentType::BASELINE,
        ]);
        $order = Order::factory()->for($run, 'simulationRun')->create();
        $shiftOrder = ShiftOrder::factory()
            ->for($courier, 'shift')
            ->for($order, 'order')
            ->create([
                'status' => OrderStatus::AVAILABLE,
                'accepted_metrics' => ['net_profit_mxn' => '90.62'],
            ]);
        $event = SimulationEvent::factory()->for($run, 'simulationRun')->create();
        $recommendation = Recommendation::factory()->for($courier, 'shift')->create();
        $delivery = Delivery::factory()
            ->for($courier, 'shift')
            ->for($recommendation, 'recommendation')
            ->create();

        $this->assertInstanceOf(ShiftStatus::class, $run->status);
        $this->assertInstanceOf(AgentType::class, $courier->agent_type);
        $this->assertInstanceOf(CourierStatus::class, $courier->courier_status);
        $this->assertSame('25.6750000', $courier->current_lat);
        $this->assertSame('1.25', $courier->cost_per_km_mxn);
        $this->assertIsArray($run->environment_state);
        $this->assertInstanceOf(OrderStatus::class, $shiftOrder->status);
        $this->assertIsArray($shiftOrder->accepted_metrics);
        $this->assertInstanceOf(EventType::class, $event->type);
        $this->assertInstanceOf(RecommendationStatus::class, $recommendation->status);
        $this->assertInstanceOf(DeliveryStatus::class, $delivery->status);
        $this->assertIsArray($recommendation->ranking);
        $this->assertSame('0.00', $recommendation->absolute_utility);
        $this->assertIsArray($delivery->order_ids);

        $this->assertInstanceOf(HasMany::class, $run->shifts());
        $this->assertInstanceOf(HasMany::class, $run->orders());
        $this->assertInstanceOf(HasMany::class, $run->events());
        $this->assertInstanceOf(BelongsTo::class, $courier->simulationRun());
        $this->assertInstanceOf(HasMany::class, $courier->shiftOrders());
        $this->assertInstanceOf(HasMany::class, $courier->recommendations());
        $this->assertInstanceOf(HasMany::class, $courier->deliveries());
        $this->assertInstanceOf(BelongsTo::class, $shiftOrder->order());
        $this->assertInstanceOf(BelongsTo::class, $recommendation->shift());
        $this->assertInstanceOf(BelongsTo::class, $delivery->recommendation());

        $this->assertTrue($run->shifts->contains($courier));
        $this->assertTrue($run->orders->contains($order));
        $this->assertTrue($courier->shiftOrders->contains($shiftOrder));
        $this->assertTrue($order->shiftOrders->contains($shiftOrder));
        $this->assertTrue($courier->recommendations->contains($recommendation));
        $this->assertTrue($courier->deliveries->contains($delivery));
        $this->assertSame(2, $run->shifts()->count());
        $this->assertSame(AgentType::BASELINE, $baseline->agent_type);
    }

    public function test_state_scopes_and_pending_event_scope_are_authoritative(): void
    {
        $run = SimulationRun::factory()->create(['status' => ShiftStatus::RUNNING]);
        $shift = Shift::factory()->for($run, 'simulationRun')->create(['status' => ShiftStatus::RUNNING]);
        $order = Order::factory()->for($run, 'simulationRun')->create();
        ShiftOrder::factory()->for($shift, 'shift')->for($order, 'order')->create([
            'status' => OrderStatus::AVAILABLE,
        ]);
        SimulationEvent::factory()->for($run, 'simulationRun')->create([
            'scheduled_at' => '2026-09-12 18:05:00',
            'applied_at' => null,
            'sequence' => 2,
        ]);
        SimulationEvent::factory()->for($run, 'simulationRun')->create([
            'scheduled_at' => '2026-09-12 18:05:00',
            'applied_at' => '2026-09-12 18:06:00',
            'sequence' => 1,
        ]);
        SimulationEvent::factory()->for($run, 'simulationRun')->create([
            'scheduled_at' => '2026-09-12 18:10:00',
        ]);

        $this->assertSame(1, SimulationRun::running()->count());
        $this->assertSame(1, Shift::running()->count());
        $this->assertSame(1, ShiftOrder::available()->count());
        $this->assertSame(0, ShiftOrder::pending()->count());
        $pending = SimulationEvent::pendingAt(Carbon::parse('2026-09-12 18:05:00'))->get();

        $this->assertCount(1, $pending);
        $this->assertSame(2, $pending->first()->sequence);
    }

    public function test_all_domain_factories_can_persist_records(): void
    {
        $run = SimulationRun::factory()->create();
        $shift = Shift::factory()->for($run, 'simulationRun')->create();
        $order = Order::factory()->for($run, 'simulationRun')->create();
        $recommendation = Recommendation::factory()->for($shift, 'shift')->create();

        $this->assertDatabaseHas('demand_zones', ['id' => DemandZone::factory()->create()->id]);
        $this->assertDatabaseHas('route_caches', ['id' => RouteCache::factory()->create()->id]);
        $benchmark = BenchmarkRun::factory()->create();
        $result = BenchmarkResult::factory()
            ->for($benchmark, 'benchmarkRun')
            ->for($run, 'simulationRun')
            ->create(['agent_type' => AgentType::COURIER_AI, 'seed' => 1]);

        $this->assertDatabaseHas('shifts', ['id' => $shift->id]);
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertDatabaseHas('recommendations', ['id' => $recommendation->id]);
        $this->assertDatabaseHas('benchmark_results', ['id' => $result->id]);
        $this->assertInstanceOf(BenchmarkStatus::class, $benchmark->status);
        $this->assertInstanceOf(BenchmarkStatus::class, $result->status);
    }
}
