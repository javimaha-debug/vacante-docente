<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // pgvector is PostgreSQL-only. On SQLite (tests) the embedding column
        // falls back to TEXT and similarity is computed in PHP (see RagService).
        //
        // Tolerate a server without the extension installed: don't let a missing
        // `vector` extension abort the whole migration chain. If it can't be
        // enabled, document_chunks falls back to a TEXT embedding column and the
        // app uses the PHP cosine path until pgvector is installed.
        if (DB::getDriverName() === 'pgsql') {
            try {
                DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
            } catch (\Throwable $e) {
                logger()->warning('pgvector no disponible; se usará el fallback en PHP. '.$e->getMessage());
            }
        }
    }

    public function down(): void
    {
        // Leave the extension installed; dropping it would break other objects.
    }
};
