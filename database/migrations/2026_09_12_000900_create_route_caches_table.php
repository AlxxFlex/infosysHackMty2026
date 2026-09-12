<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_caches', function (Blueprint $table) {
            $table->id();
            $table->char('cache_key', 64)->unique();
            $table->string('provider', 50);
            $table->decimal('origin_lat', 10, 7);
            $table->decimal('origin_lon', 10, 7);
            $table->decimal('destination_lat', 10, 7);
            $table->decimal('destination_lon', 10, 7);
            $table->string('traffic_bucket', 50)->default('normal');
            $table->unsignedInteger('closure_version')->default(0);
            $table->decimal('distance_km', 14, 3);
            $table->decimal('duration_minutes', 10, 2);
            $table->json('geometry_geojson')->nullable();
            $table->json('response_summary')->nullable();
            $table->dateTime('expires_at')->index();
            $table->timestamps();

            $table->index(
                ['provider', 'traffic_bucket', 'closure_version'],
                'route_caches_provider_environment_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_caches');
    }
};
