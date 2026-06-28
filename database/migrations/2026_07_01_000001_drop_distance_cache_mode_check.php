<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The original `enum('mode', …)` created a CHECK constraint in
        // PostgreSQL (distance_cache_mode_check) that only allowed
        // driving/transit/walking. Widening the column to a string did NOT drop
        // it, so composite keys like "driving_tornada" violate it and the whole
        // distance calculation fails. Drop the leftover constraint.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE distance_cache DROP CONSTRAINT IF EXISTS distance_cache_mode_check');
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: re-adding the narrow constraint would
        // again break composite mode keys.
    }
};
