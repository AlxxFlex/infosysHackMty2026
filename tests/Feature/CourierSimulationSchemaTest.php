<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CourierSimulationSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($connection !== 'mysql' || $database !== 'infoSys_testing') {
            throw new \LogicException(
                'Schema tests may only refresh the dedicated MySQL database infoSys_testing.'
            );
        }
    }

    public function test_shared_offer_has_independent_state_for_each_agent(): void
    {
        $runId = $this->createRun();
        $courierShiftId = $this->createShift($runId, 'COURIER_AI');
        $baselineShiftId = $this->createShift($runId, 'BASELINE');
        $orderId = $this->createOrder($runId);

        DB::table('shift_orders')->insert([
            'shift_id' => $courierShiftId,
            'order_id' => $orderId,
            'status' => 'ACCEPTED',
            'accepted_at' => '2026-09-12 18:02:00',
            'accepted_metrics' => json_encode(['net_profit_mxn' => '90.62']),
            'plan_position' => 1,
        ]);
        DB::table('shift_orders')->insert([
            'shift_id' => $baselineShiftId,
            'order_id' => $orderId,
            'status' => 'REJECTED',
            'rejected_at' => '2026-09-12 18:02:00',
        ]);

        $this->assertDatabaseHas('shift_orders', [
            'shift_id' => $courierShiftId,
            'order_id' => $orderId,
            'status' => 'ACCEPTED',
        ]);
        $this->assertDatabaseHas('shift_orders', [
            'shift_id' => $baselineShiftId,
            'order_id' => $orderId,
            'status' => 'REJECTED',
        ]);
        $this->assertSame(2, DB::table('shift_orders')->where('order_id', $orderId)->count());
    }

    public function test_an_agent_type_cannot_be_duplicated_within_a_run(): void
    {
        $runId = $this->createRun();
        $this->createShift($runId, 'COURIER_AI');

        $this->expectException(QueryException::class);

        $this->createShift($runId, 'COURIER_AI');
    }

    public function test_a_scenario_order_key_cannot_be_duplicated_within_a_run(): void
    {
        $runId = $this->createRun();
        $this->createOrder($runId, 'ORD-001', 'demo-order-1');

        $this->expectException(QueryException::class);

        $this->createOrder($runId, 'ORD-002', 'demo-order-1');
    }

    public function test_foreign_keys_reject_orphans(): void
    {
        $this->expectException(QueryException::class);

        $this->createShift(999999, 'COURIER_AI');
    }

    public function test_deleting_a_run_cascades_agent_specific_and_shared_records(): void
    {
        $runId = $this->createRun();
        $shiftId = $this->createShift($runId, 'COURIER_AI');
        $orderId = $this->createOrder($runId);

        DB::table('shift_orders')->insert([
            'shift_id' => $shiftId,
            'order_id' => $orderId,
            'status' => 'AVAILABLE',
            'available_at' => '2026-09-12 18:00:00',
        ]);

        DB::table('simulation_events')->insert([
            'simulation_run_id' => $runId,
            'type' => 'TRAFFIC_CHANGED',
            'scheduled_at' => '2026-09-12 18:10:00',
            'payload' => json_encode(['traffic_factor' => 1.4, 'is_simulated' => true]),
        ]);

        $recommendationId = DB::table('recommendations')->insertGetId([
            'shift_id' => $shiftId,
            'simulation_time' => '2026-09-12 18:02:00',
            'config_version' => 'test-v1',
            'ranking' => json_encode([['order_id' => 'ORD-001', 'rank' => 1]]),
            'selected_order_ids' => json_encode([$orderId]),
            'route_sequence' => json_encode(['PICKUP:ORD-001', 'DROPOFF:ORD-001']),
            'metrics' => json_encode(['is_simulated' => true]),
            'reason_codes' => json_encode(['HIGH_HOURLY_RATE']),
            'visual_score' => '91.20',
            'absolute_utility' => '95.50',
            'expected_net_profit_mxn' => '90.62',
            'expected_minutes' => '23.00',
            'expected_distance_km' => '5.900',
            'expected_hourly_rate_mxn' => '236.40',
            'estimated_risk' => '0.080000',
        ]);

        DB::table('deliveries')->insert([
            'shift_id' => $shiftId,
            'recommendation_id' => $recommendationId,
            'status' => 'PENDING',
            'route_sequence' => json_encode(['PICKUP:ORD-001', 'DROPOFF:ORD-001']),
            'order_ids' => json_encode([$orderId]),
            'simulated_started_at' => '2026-09-12 18:02:00',
        ]);

        DB::table('simulation_runs')->where('id', $runId)->delete();

        $this->assertDatabaseMissing('simulation_runs', ['id' => $runId]);
        $this->assertDatabaseMissing('shifts', ['id' => $shiftId]);
        $this->assertDatabaseMissing('orders', ['id' => $orderId]);
        $this->assertDatabaseCount('shift_orders', 0);
        $this->assertDatabaseCount('simulation_events', 0);
        $this->assertDatabaseCount('recommendations', 0);
        $this->assertDatabaseCount('deliveries', 0);
    }

    public function test_money_and_coordinates_use_the_required_decimal_precision(): void
    {
        $runId = $this->createRun();
        $shiftId = $this->createShift($runId, 'COURIER_AI', [
            'initial_lat' => '25.1234567',
            'initial_lon' => '-100.7654321',
            'current_lat' => '25.1234567',
            'current_lon' => '-100.7654321',
            'cost_per_km_mxn' => '1.25',
            'gross_earnings_mxn' => '123.45',
        ]);

        $shift = DB::table('shifts')->find($shiftId);

        $this->assertSame('25.1234567', (string) $shift->initial_lat);
        $this->assertSame('-100.7654321', (string) $shift->initial_lon);
        $this->assertSame('1.25', (string) $shift->cost_per_km_mxn);
        $this->assertSame('123.45', (string) $shift->gross_earnings_mxn);

        $columns = collect(DB::select(<<<'SQL'
            SELECT
                column_name AS column_key,
                data_type AS column_type,
                numeric_scale AS column_scale
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'shifts'
              AND column_name IN ('initial_lat', 'initial_lon', 'cost_per_km_mxn', 'gross_earnings_mxn')
            SQL))->keyBy('column_key');

        $this->assertSame('decimal', $columns['initial_lat']->column_type);
        $this->assertSame(7, (int) $columns['initial_lat']->column_scale);
        $this->assertSame(7, (int) $columns['initial_lon']->column_scale);
        $this->assertSame(2, (int) $columns['cost_per_km_mxn']->column_scale);
        $this->assertSame(2, (int) $columns['gross_earnings_mxn']->column_scale);
    }

    public function test_complete_schema_and_query_indexes_exist(): void
    {
        $schema = [
            'simulation_runs' => [
                'scenario_key', 'seed', 'status', 'simulated_started_at',
                'simulated_current_at', 'simulated_ends_at', 'real_last_tick_at',
                'traffic_factor', 'closure_version', 'environment_state', 'config_snapshot',
            ],
            'shifts' => [
                'simulation_run_id', 'agent_type', 'status', 'courier_status',
                'initial_lat', 'initial_lon', 'current_lat', 'current_lon',
                'gross_earnings_mxn', 'net_earnings_mxn', 'operating_cost_mxn',
                'total_distance_km', 'deadhead_distance_km',
            ],
            'orders' => [
                'simulation_run_id', 'external_id', 'scenario_order_key', 'spawn_time',
                'expires_at', 'restaurant_lat', 'restaurant_lon', 'customer_lat',
                'customer_lon', 'base_pay_mxn', 'surge_bonus_mxn', 'other_bonus_mxn',
                'restrictions', 'metadata',
            ],
            'shift_orders' => [
                'shift_id', 'order_id', 'status', 'available_at', 'accepted_at',
                'picked_up_at', 'delivered_at', 'rejected_at', 'expired_at',
                'accepted_metrics', 'plan_position',
            ],
            'demand_zones' => [
                'zone_key', 'center_lat', 'center_lon', 'polygon_geojson',
                'demand_by_hour', 'is_active',
            ],
            'simulation_events' => [
                'simulation_run_id', 'type', 'scheduled_at', 'applied_at', 'payload',
                'state_before', 'state_after', 'reason_codes',
            ],
            'recommendations' => [
                'shift_id', 'simulation_time', 'ranking', 'selected_order_ids',
                'route_sequence', 'metrics', 'reason_codes', 'visual_score',
                'absolute_utility', 'expected_net_profit_mxn', 'expected_minutes',
                'expected_distance_km', 'expected_hourly_rate_mxn', 'estimated_risk',
            ],
            'deliveries' => [
                'shift_id', 'recommendation_id', 'status', 'route_sequence', 'order_ids',
                'simulated_started_at', 'simulated_finished_at', 'gross_earnings_mxn',
                'operating_cost_mxn', 'net_earnings_mxn', 'total_distance_km',
                'deadhead_distance_km', 'simulated_duration_minutes', 'lateness_minutes',
            ],
            'route_caches' => [
                'cache_key', 'provider', 'origin_lat', 'origin_lon', 'destination_lat',
                'destination_lon', 'traffic_bucket', 'closure_version', 'distance_km',
                'duration_minutes', 'geometry_geojson', 'response_summary', 'expires_at',
            ],
            'benchmark_runs' => [
                'scenario_key', 'seeds', 'config_snapshot', 'status', 'total_seeds',
                'completed_seeds', 'failed_seeds', 'started_at', 'finished_at',
            ],
            'benchmark_results' => [
                'benchmark_run_id', 'simulation_run_id', 'seed', 'agent_type', 'status',
                'gross_earnings_mxn', 'operating_cost_mxn', 'net_earnings_mxn',
                'net_hourly_rate_mxn', 'total_distance_km', 'deadhead_distance_km',
                'active_minutes', 'idle_minutes', 'completed_orders_count',
                'late_orders_count', 'productive_time_percent',
            ],
        ];

        foreach ($schema as $table => $columns) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
            $this->assertTrue(
                Schema::hasColumns($table, $columns),
                "Missing required columns in table: {$table}"
            );
        }

        $indexes = collect(DB::select(<<<'SQL'
            SELECT DISTINCT index_name AS index_key
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name IN (
                'simulation_runs', 'shifts', 'orders', 'shift_orders',
                'simulation_events', 'recommendations', 'route_caches',
                'benchmark_results'
              )
            SQL))->pluck('index_key');

        foreach ([
            'simulation_runs_scenario_seed_index',
            'simulation_runs_status_time_index',
            'shifts_run_agent_unique',
            'shifts_run_status_index',
            'orders_run_scenario_key_unique',
            'orders_run_spawn_index',
            'orders_run_expires_index',
            'shift_orders_shift_order_unique',
            'shift_orders_shift_status_index',
            'simulation_events_run_schedule_applied_index',
            'recommendations_shift_time_index',
            'route_caches_cache_key_unique',
            'benchmark_results_run_seed_agent_unique',
        ] as $index) {
            $this->assertContains($index, $indexes, "Missing index: {$index}");
        }
    }

    public function test_domain_schema_contains_no_float_or_double_columns(): void
    {
        $invalidColumns = DB::select(<<<'SQL'
            SELECT table_name, column_name, data_type
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name IN (
                'simulation_runs', 'shifts', 'orders', 'shift_orders',
                'demand_zones', 'simulation_events', 'recommendations',
                'deliveries', 'route_caches', 'benchmark_runs', 'benchmark_results'
              )
              AND data_type IN ('float', 'double', 'real')
            SQL);

        $this->assertSame([], $invalidColumns);
    }

    private function createRun(array $overrides = []): int
    {
        return DB::table('simulation_runs')->insertGetId(array_merge([
            'scenario_key' => 'demo_normal',
            'seed' => 42,
            'status' => 'IDLE',
            'simulated_started_at' => '2026-09-12 18:00:00',
            'simulated_current_at' => '2026-09-12 18:00:00',
            'simulated_ends_at' => '2026-09-12 20:00:00',
            'traffic_factor' => '1.0000',
            'weather' => 'clear',
            'closure_version' => 0,
            'speed_multiplier' => '1.0000',
            'environment_state' => json_encode(['is_simulated' => true]),
            'config_snapshot' => json_encode(['version' => 'test-v1']),
        ], $overrides));
    }

    private function createShift(int $runId, string $agentType, array $overrides = []): int
    {
        return DB::table('shifts')->insertGetId(array_merge([
            'simulation_run_id' => $runId,
            'agent_type' => $agentType,
            'status' => 'IDLE',
            'courier_status' => 'AVAILABLE',
            'initial_lat' => '25.6750000',
            'initial_lon' => '-100.3100000',
            'current_lat' => '25.6750000',
            'current_lon' => '-100.3100000',
            'max_concurrent_orders' => 2,
            'cost_per_km_mxn' => '1.25',
        ], $overrides));
    }

    private function createOrder(
        int $runId,
        string $externalId = 'ORD-001',
        string $scenarioOrderKey = 'demo-order-1'
    ): int {
        return DB::table('orders')->insertGetId([
            'simulation_run_id' => $runId,
            'external_id' => $externalId,
            'scenario_order_key' => $scenarioOrderKey,
            'spawn_time' => '2026-09-12 18:00:00',
            'expires_at' => '2026-09-12 18:05:00',
            'pickup_deadline' => '2026-09-12 18:20:00',
            'delivery_deadline' => '2026-09-12 18:45:00',
            'restaurant_id' => 'REST-001',
            'restaurant_name' => 'Demo Restaurant',
            'restaurant_lat' => '25.6710000',
            'restaurant_lon' => '-100.3090000',
            'customer_lat' => '25.6860000',
            'customer_lon' => '-100.2960000',
            'destination_zone' => 'CENTRO',
            'base_pay_mxn' => '78.00',
            'surge_bonus_mxn' => '20.00',
            'other_bonus_mxn' => '0.00',
            'estimated_restaurant_wait_min' => 5,
            'restrictions' => json_encode([]),
            'metadata' => json_encode(['is_simulated' => true]),
        ]);
    }
}
