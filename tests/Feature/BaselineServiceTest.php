<?php

namespace Tests\Feature;

use App\Enums\AgentType;
use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Models\Delivery;
use App\Models\Shift;
use App\Models\SimulationRun;
use App\Services\BaselineService;
use App\Services\PlanExecutionService;
use App\Services\ShiftService;
use App\Services\SimulatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BaselineServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");
        if ($connection !== 'mysql' || $database !== 'infoSys_testing') {
            throw new \LogicException('Baseline tests may only refresh the dedicated MySQL database infoSys_testing.');
        }
    }

    public function test_baseline_selects_highest_gross_pay_not_highest_hourly_rate(): void
    {
        config()->set('courier.routing.provider', 'fallback');
        Cache::flush();
        [$run, $baseline] = $this->availableBaseline();
        $fast = $run->orders()->where('external_id', 'DEMO-001')->firstOrFail();
        $slow = $run->orders()->where('external_id', 'DEMO-002')->firstOrFail();
        $fast->forceFill(['base_pay_mxn' => '90.00', 'surge_bonus_mxn' => '0.00', 'other_bonus_mxn' => '0.00', 'estimated_restaurant_wait_min' => 0])->save();
        $slow->forceFill(['base_pay_mxn' => '110.00', 'surge_bonus_mxn' => '0.00', 'other_bonus_mxn' => '0.00', 'estimated_restaurant_wait_min' => 20])->save();

        $selected = app(BaselineService::class)->select($baseline, $run);

        $this->assertNotNull($selected);
        $this->assertSame('DEMO-002', $selected->orderId);
    }

    public function test_infeasible_highest_payment_is_skipped_and_ties_use_time_then_stable_key(): void
    {
        config()->set('courier.routing.provider', 'fallback');
        Cache::flush();
        [$run, $baseline] = $this->availableBaseline();
        $high = $run->orders()->where('external_id', 'DEMO-002')->firstOrFail();
        $high->forceFill(['base_pay_mxn' => '250.00', 'delivery_deadline' => '2026-09-12 17:00:00'])->save();
        $selected = app(BaselineService::class)->select($baseline, $run);
        $this->assertSame('DEMO-001', $selected?->orderId);

        $orders = $run->orders()->whereIn('external_id', ['DEMO-001', 'DEMO-002'])->get();
        $orders->each(fn ($order) => $order->forceFill([
            'base_pay_mxn' => '100.00',
            'delivery_deadline' => '2026-09-12 19:00:00',
            'estimated_restaurant_wait_min' => 0,
            'restaurant_lat' => '25.6750000',
            'restaurant_lon' => '-100.3100000',
            'customer_lat' => '25.6800000',
            'customer_lon' => '-100.3000000',
        ])->save());
        Cache::flush();
        $tied = app(BaselineService::class)->select($baseline, $run);
        $again = app(BaselineService::class)->select($baseline, $run);
        $this->assertNotNull($tied);
        $this->assertSame($tied->orderId, $again?->orderId);
    }

    public function test_baseline_never_forms_a_batch_and_execution_is_shared_and_idempotent(): void
    {
        config()->set('courier.routing.provider', 'fallback');
        Cache::flush();
        [$run, $baseline] = $this->availableBaseline();
        $ai = $run->fresh('shifts')->shifts->firstWhere('agent_type', AgentType::COURIER_AI);
        $selected = app(BaselineService::class)->select($baseline, $run);
        $this->assertNotNull($selected);
        $this->assertCount(1, [$selected->orderId]);

        $delivery = app(PlanExecutionService::class)->acceptAndExecute($baseline, $run, $selected);
        $this->assertSame(DeliveryStatus::IN_PROGRESS, $delivery->status);
        $baselineOrder = $baseline->shiftOrders()->whereHas('order', fn ($query) => $query->where('external_id', $selected->orderId))->firstOrFail();
        $aiOrder = $ai->shiftOrders()->whereHas('order', fn ($query) => $query->where('external_id', $selected->orderId))->firstOrFail();
        $this->assertSame(OrderStatus::ACCEPTED, $baselineOrder->status);
        $this->assertSame(OrderStatus::AVAILABLE, $aiOrder->status);

        $advanced = app(SimulatorService::class)->tick($run, 120, 'baseline-complete');
        $completed = Delivery::query()->findOrFail($delivery->id);
        $this->assertSame(DeliveryStatus::COMPLETED, $completed->status);
        $baselineAfter = $advanced->fresh('shifts')->shifts->firstWhere('agent_type', AgentType::BASELINE);
        $gross = (string) $baselineAfter->gross_earnings_mxn;
        $net = (string) $baselineAfter->net_earnings_mxn;
        $this->assertSame($completed->gross_earnings_mxn, $gross);
        $this->assertSame($completed->net_earnings_mxn, $net);
        $this->assertSame(1, $baselineAfter->completed_orders_count);

        app(SimulatorService::class)->tick($advanced, 1, 'baseline-after-complete');
        $baselineAgain = $baselineAfter->fresh();
        $this->assertSame($gross, (string) $baselineAgain->gross_earnings_mxn);
        $this->assertSame(1, $baselineAgain->completed_orders_count);
    }

    /** @return array{0: SimulationRun, 1: Shift} */
    private function availableBaseline(): array
    {
        $shiftService = app(ShiftService::class);
        $run = $shiftService->startRun($shiftService->createRun('demo_normal', 21));
        $run = app(SimulatorService::class)->tick($run, 1, 'baseline-availability');
        $baseline = $run->fresh('shifts')->shifts->firstWhere('agent_type', AgentType::BASELINE);

        return [$run, $baseline];
    }
}
