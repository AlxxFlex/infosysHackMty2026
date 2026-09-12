<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demand_zones', function (Blueprint $table) {
            $table->id();
            $table->string('zone_key', 100)->unique();
            $table->string('name', 150);
            $table->decimal('center_lat', 10, 7);
            $table->decimal('center_lon', 10, 7);
            $table->json('polygon_geojson')->nullable();
            $table->json('demand_by_hour');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demand_zones');
    }
};
