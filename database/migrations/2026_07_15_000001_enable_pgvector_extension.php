<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // pgvector is PostgreSQL-only. On SQLite (tests) the embedding column
        // falls back to TEXT and similarity is computed in PHP (see RagService).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        }
    }

    public function down(): void
    {
        // Leave the extension installed; dropping it would break other objects.
    }
};
