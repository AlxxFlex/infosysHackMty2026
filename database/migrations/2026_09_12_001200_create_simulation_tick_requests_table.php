<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulation_tick_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('simulation_run_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('idempotency_key', 128);
            $table->unsignedInteger('requested_minutes');
            $table->dateTime('simulated_from');
            $table->dateTime('simulated_to');
            $table->timestamps();

            $table->unique(
                ['simulation_run_id', 'idempotency_key'],
                'simulation_tick_requests_run_key_unique'
            );
            $table->index(
                ['simulation_run_id', 'created_at'],
                'simulation_tick_requests_run_created_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulation_tick_requests');
    }
};
