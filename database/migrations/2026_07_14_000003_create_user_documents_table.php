<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('user_folders')->nullOnDelete();
            $table->string('name', 500);                 // original filename shown to user
            $table->string('disk_path', 700);            // path in Spaces storage
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('type', 10)->default('other');     // pdf | word | image | other
            $table->string('source', 20)->default('upload');  // upload | google_drive | microsoft_365
            $table->string('external_id', 255)->nullable();
            $table->string('external_url', 700)->nullable();
            $table->string('processing_status', 20)->default('pending'); // pending|processing|ready|failed
            $table->unsignedInteger('page_count')->nullable();
            $table->unsignedInteger('word_count')->nullable();
            $table->string('thumbnail_path', 700)->nullable();
            $table->unsignedBigInteger('tema_id')->nullable(); // oposicion_temas (no FK yet)
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'folder_id']);
            $table->index(['user_id', 'type']);
            $table->index('processing_status');
            $table->index('tema_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_documents');
    }
};
