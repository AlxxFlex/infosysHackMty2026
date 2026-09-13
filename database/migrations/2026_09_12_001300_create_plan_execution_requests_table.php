<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_execution_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->string('idempotency_key', 128);
            $table->foreignId('delivery_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['shift_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_execution_requests');
    }
};
