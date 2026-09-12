<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('simulation_run_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('type', 50);
            $table->dateTime('scheduled_at');
            $table->dateTime('applied_at')->nullable();
            $table->unsignedInteger('sequence')->default(0);
            $table->json('payload');
            $table->json('state_before')->nullable();
            $table->json('state_after')->nullable();
            $table->json('reason_codes')->nullable();
            $table->timestamps();

            $table->index(
                ['simulation_run_id', 'scheduled_at', 'applied_at'],
                'simulation_events_run_schedule_applied_index'
            );
            $table->index(
                ['simulation_run_id', 'scheduled_at', 'sequence'],
                'simulation_events_run_order_index'
            );
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulation_events');
    }
};
