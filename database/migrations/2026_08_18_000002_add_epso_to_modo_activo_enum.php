<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;
        DB::statement("ALTER TABLE users ALTER COLUMN modo_activo TYPE VARCHAR(20)");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_modo_activo_check CHECK (modo_activo IN ('bolsa', 'oposicion', 'docente', 'epso'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;
        DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_modo_activo_check");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_modo_activo_check CHECK (modo_activo IN ('bolsa', 'oposicion', 'docente'))");
    }
};
