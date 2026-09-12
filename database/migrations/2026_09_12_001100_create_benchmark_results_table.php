<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('benchmark_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('benchmark_run_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('simulation_run_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->unsignedBigInteger('seed');
            $table->string('agent_type', 30);
            $table->string('status', 30)->default('COMPLETED');
            $table->decimal('gross_earnings_mxn', 14, 2)->default(0);
            $table->decimal('operating_cost_mxn', 14, 2)->default(0);
            $table->decimal('net_earnings_mxn', 14, 2)->default(0);
            $table->decimal('net_hourly_rate_mxn', 14, 2)->default(0);
            $table->decimal('total_distance_km', 14, 3)->default(0);
            $table->decimal('deadhead_distance_km', 14, 3)->default(0);
            $table->unsignedInteger('active_minutes')->default(0);
            $table->unsignedInteger('idle_minutes')->default(0);
            $table->unsignedInteger('accepted_orders_count')->default(0);
            $table->unsignedInteger('rejected_orders_count')->default(0);
            $table->unsignedInteger('completed_orders_count')->default(0);
            $table->unsignedInteger('late_orders_count')->default(0);
            $table->decimal('productive_time_percent', 6, 2)->default(0);
            $table->json('metrics')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(
                ['benchmark_run_id', 'seed', 'agent_type'],
                'benchmark_results_run_seed_agent_unique'
            );
            $table->index(
                ['benchmark_run_id', 'status'],
                'benchmark_results_run_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benchmark_results');
    }
};
