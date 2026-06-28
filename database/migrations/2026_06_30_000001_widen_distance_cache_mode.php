<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Allow composite keys like "driving_ida" / "transit_tornada" so a
        // single cache can hold both directions and all three travel modes.
        Schema::table('distance_cache', function (Blueprint $table) {
            $table->string('mode', 20)->change();
        });
    }

    public function down(): void
    {
        Schema::table('distance_cache', function (Blueprint $table) {
            $table->enum('mode', ['driving', 'transit', 'walking'])->change();
        });
    }
};
