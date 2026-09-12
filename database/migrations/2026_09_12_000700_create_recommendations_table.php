<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->dateTime('simulation_time');
            $table->string('config_version', 100);
            $table->json('ranking');
            $table->json('selected_order_ids');
            $table->json('route_sequence');
            $table->json('metrics');
            $table->json('reason_codes');
            $table->decimal('visual_score', 6, 2)->nullable();
            $table->decimal('absolute_utility', 14, 2);
            $table->decimal('expected_net_profit_mxn', 14, 2);
            $table->decimal('expected_minutes', 10, 2);
            $table->decimal('expected_distance_km', 14, 3);
            $table->decimal('expected_hourly_rate_mxn', 14, 2);
            $table->decimal('estimated_risk', 8, 6);
            $table->string('status', 30)->default('PENDING');
            $table->text('deterministic_explanation')->nullable();
            $table->text('llm_explanation')->nullable();
            $table->unsignedInteger('routing_duration_ms')->default(0);
            $table->unsignedInteger('optimization_duration_ms')->default(0);
            $table->unsignedInteger('candidates_evaluated')->default(0);
            $table->timestamps();

            $table->index(
                ['shift_id', 'simulation_time'],
                'recommendations_shift_time_index'
            );
            $table->index(
                ['shift_id', 'status'],
                'recommendations_shift_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendations');
    }
};
