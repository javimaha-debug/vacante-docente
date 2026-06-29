<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Documents the monitor detected on a source; a superadmin reviews,
        // validates/rejects and finally publishes them to users.
        Schema::create('detected_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->nullable()->constrained('monitored_sources')->nullOnDelete();
            $table->string('title', 500);
            $table->timestamp('detected_at')->nullable();
            $table->string('source_url', 700)->nullable();
            // listado_provisional | listado_definitivo | vacantes | resolucion | convocatoria | otro
            $table->string('document_type', 30)->default('otro');
            // pending | validated | rejected | published
            $table->string('status', 20)->default('pending');
            $table->string('pdf_url', 700)->nullable();
            $table->string('pdf_path', 500)->nullable();        // local storage path
            $table->text('superadmin_notes')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('document_type');
            $table->index(['source_id', 'detected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detected_documents');
    }
};
