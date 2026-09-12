<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulation_runs', function (Blueprint $table) {
            $table->id();
            $table->string('scenario_key', 100)->index();
            $table->unsignedBigInteger('seed');
            $table->string('status', 30)->index();
            $table->dateTime('simulated_started_at');
            $table->dateTime('simulated_current_at');
            $table->dateTime('simulated_ends_at');
            $table->dateTime('real_started_at')->nullable();
            $table->dateTime('real_finished_at')->nullable();
            $table->dateTime('real_last_tick_at')->nullable();
            $table->decimal('traffic_factor', 8, 4)->default(1);
            $table->string('weather', 50)->default('clear');
            $table->unsignedInteger('closure_version')->default(0);
            $table->decimal('speed_multiplier', 8, 4)->default(1);
            $table->json('environment_state')->nullable();
            $table->json('config_snapshot');
            $table->timestamps();

            $table->index(
                ['scenario_key', 'seed'],
                'simulation_runs_scenario_seed_index'
            );
            $table->index(
                ['status', 'simulated_current_at'],
                'simulation_runs_status_time_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulation_runs');
    }
};
