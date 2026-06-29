<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_document_id')->constrained('user_documents')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('chunk_index');
            $table->integer('page_number')->nullable();
            $table->text('content');
            $table->integer('token_count')->default(0);
            // `embedding` added below: pgvector vector(1024) on Postgres, TEXT
            // (JSON array) elsewhere so the test SQLite schema stays valid.
            $table->timestamps();

            $table->index(['user_id']);
            $table->index(['user_document_id', 'chunk_index']);
        });

        // Use a real pgvector column + index only when the extension is actually
        // present; otherwise (SQLite, MySQL, or Postgres without pgvector) store
        // the embedding as TEXT so the migration never fails and the app uses the
        // PHP cosine fallback.
        $hasPgVector = DB::getDriverName() === 'pgsql'
            && ! empty(DB::select("SELECT 1 FROM pg_extension WHERE extname = 'vector'"));

        if ($hasPgVector) {
            DB::statement('ALTER TABLE document_chunks ADD COLUMN embedding vector(1024)');
            DB::statement('CREATE INDEX document_chunks_embedding_idx ON document_chunks USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');
        } else {
            Schema::table('document_chunks', function (Blueprint $table) {
                $table->text('embedding')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
