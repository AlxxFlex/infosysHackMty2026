<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('recommendation_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('status', 30);
            $table->json('route_sequence');
            $table->json('order_ids');
            $table->dateTime('simulated_started_at');
            $table->dateTime('simulated_finished_at')->nullable();
            $table->decimal('gross_earnings_mxn', 14, 2)->default(0);
            $table->decimal('operating_cost_mxn', 14, 2)->default(0);
            $table->decimal('net_earnings_mxn', 14, 2)->default(0);
            $table->decimal('total_distance_km', 14, 3)->default(0);
            $table->decimal('deadhead_distance_km', 14, 3)->default(0);
            $table->decimal('simulated_duration_minutes', 10, 2)->default(0);
            $table->unsignedInteger('lateness_minutes')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['shift_id', 'status'], 'deliveries_shift_status_index');
            $table->index(
                ['shift_id', 'simulated_started_at'],
                'deliveries_shift_started_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
