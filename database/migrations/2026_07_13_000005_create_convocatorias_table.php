<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convocatorias', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->string('comunidad_autonoma');
            $table->string('cuerpo')->nullable();
            $table->json('especialidades')->nullable();
            $table->enum('estado', ['rumor', 'anunciada', 'convocada', 'en_proceso', 'resuelta']);
            $table->date('fecha_estimada')->nullable();
            $table->date('fecha_oficial')->nullable();
            $table->string('url_oficial')->nullable();
            $table->string('boe_url')->nullable();
            $table->text('notas')->nullable();
            // The "detected document" concept in this codebase lives in gva_noticias.
            $table->foreignId('source_document_id')->nullable()->constrained('gva_noticias')->nullOnDelete();
            $table->timestamps();

            $table->index('estado');
            $table->index('comunidad_autonoma');
            $table->index('cuerpo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convocatorias');
    }
};
