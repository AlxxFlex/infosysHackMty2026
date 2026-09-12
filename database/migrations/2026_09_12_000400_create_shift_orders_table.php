<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')
                ->comment('Agent-specific lifecycle for the shared offer.')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('status', 30);
            $table->dateTime('available_at')->nullable();
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('picked_up_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->dateTime('expired_at')->nullable();
            $table->json('accepted_metrics')->nullable();
            $table->unsignedSmallInteger('plan_position')->nullable();
            $table->timestamps();

            $table->unique(['shift_id', 'order_id'], 'shift_orders_shift_order_unique');
            $table->index(['shift_id', 'status'], 'shift_orders_shift_status_index');
            $table->index(['order_id', 'status'], 'shift_orders_order_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_orders');
    }
};
