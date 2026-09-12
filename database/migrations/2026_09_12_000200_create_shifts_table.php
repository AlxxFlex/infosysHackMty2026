<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('simulation_run_id')
                ->comment('Both agents belong to the same reproducible run.')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('agent_type', 30);
            $table->string('status', 30);
            $table->string('courier_status', 40);
            $table->decimal('initial_lat', 10, 7);
            $table->decimal('initial_lon', 10, 7);
            $table->decimal('current_lat', 10, 7);
            $table->decimal('current_lon', 10, 7);
            $table->unsignedTinyInteger('max_concurrent_orders')->default(2);
            $table->decimal('cost_per_km_mxn', 10, 2);
            $table->decimal('gross_earnings_mxn', 14, 2)->default(0);
            $table->decimal('net_earnings_mxn', 14, 2)->default(0);
            $table->decimal('operating_cost_mxn', 14, 2)->default(0);
            $table->decimal('total_distance_km', 14, 3)->default(0);
            $table->decimal('deadhead_distance_km', 14, 3)->default(0);
            $table->unsignedInteger('active_minutes')->default(0);
            $table->unsignedInteger('idle_minutes')->default(0);
            $table->unsignedInteger('accepted_orders_count')->default(0);
            $table->unsignedInteger('rejected_orders_count')->default(0);
            $table->unsignedInteger('completed_orders_count')->default(0);
            $table->unsignedInteger('late_orders_count')->default(0);
            $table->timestamps();

            $table->unique(
                ['simulation_run_id', 'agent_type'],
                'shifts_run_agent_unique'
            );
            $table->index(
                ['simulation_run_id', 'status'],
                'shifts_run_status_index'
            );
            $table->index('courier_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
