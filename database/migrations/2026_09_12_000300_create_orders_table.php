<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('simulation_run_id')
                ->comment('Offer definition shared by Courier AI and baseline.')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('external_id', 100);
            $table->string('scenario_order_key', 100);
            $table->dateTime('spawn_time');
            $table->dateTime('expires_at');
            $table->dateTime('pickup_deadline')->nullable();
            $table->dateTime('delivery_deadline')->nullable();
            $table->string('restaurant_id', 100);
            $table->string('restaurant_name', 150);
            $table->decimal('restaurant_lat', 10, 7);
            $table->decimal('restaurant_lon', 10, 7);
            $table->decimal('customer_lat', 10, 7);
            $table->decimal('customer_lon', 10, 7);
            $table->string('destination_zone', 100)->nullable();
            $table->decimal('base_pay_mxn', 12, 2);
            $table->decimal('surge_bonus_mxn', 12, 2)->default(0);
            $table->decimal('other_bonus_mxn', 12, 2)->default(0);
            $table->unsignedInteger('estimated_restaurant_wait_min')->default(0);
            $table->json('restrictions')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['simulation_run_id', 'scenario_order_key'],
                'orders_run_scenario_key_unique'
            );
            $table->unique(
                ['simulation_run_id', 'external_id'],
                'orders_run_external_id_unique'
            );
            $table->index(
                ['simulation_run_id', 'spawn_time'],
                'orders_run_spawn_index'
            );
            $table->index(
                ['simulation_run_id', 'expires_at'],
                'orders_run_expires_index'
            );
            $table->index('destination_zone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
