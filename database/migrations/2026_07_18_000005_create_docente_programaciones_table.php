<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docente_programaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asignatura_id')->constrained('docente_asignaturas')->cascadeOnDelete();
            $table->string('titulo', 200);
            $table->string('año_academico', 9);
            $table->string('centro_nombre', 200)->nullable();
            $table->string('centro_tipo', 20)->nullable(); // CEIP, IES...
            $table->boolean('es_bilingue')->default(false);
            $table->text('objetivos_generales')->nullable();
            $table->text('metodologia')->nullable();
            $table->text('atencion_diversidad')->nullable();
            $table->text('criterios_evaluacion')->nullable();
            $table->text('instrumentos_evaluacion')->nullable();
            $table->enum('status', ['borrador', 'activa', 'archivada'])->default('borrador');
            $table->foreignId('document_id')->nullable()->constrained('user_documents')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'año_academico']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docente_programaciones');
    }
};
