<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distance_cache', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vacancy_id')->constrained('vacancies')->cascadeOnDelete();
            $table->decimal('home_lat', 10, 7);
            $table->decimal('home_lng', 10, 7);
            $table->enum('mode', ['driving', 'transit', 'walking']);
            $table->integer('duration_minutes')->nullable();
            $table->decimal('distance_km', 6, 2)->nullable();
            $table->string('traffic_note', 100)->nullable();
            $table->timestamp('calculated_at')->useCurrent();

            $table->index(['vacancy_id', 'home_lat', 'home_lng', 'mode'], 'distance_cache_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distance_cache');
    }
};
