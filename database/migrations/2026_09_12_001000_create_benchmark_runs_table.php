<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('benchmark_runs', function (Blueprint $table) {
            $table->id();
            $table->string('scenario_key', 100)->index();
            $table->json('seeds');
            $table->json('config_snapshot');
            $table->string('config_version', 100);
            $table->string('algorithm_version', 100)->nullable();
            $table->string('status', 30)->index();
            $table->unsignedInteger('total_seeds');
            $table->unsignedInteger('completed_seeds')->default(0);
            $table->unsignedInteger('failed_seeds')->default(0);
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benchmark_runs');
    }
};
