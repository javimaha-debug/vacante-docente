<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docente_asignaturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->string('codigo_asignatura', 20)->nullable();
            $table->enum('etapa', ['infantil', 'primaria', 'eso', 'bachillerato', 'fp', 'otros']);
            $table->string('curso', 30);
            $table->string('año_academico', 9); // '2026-2027'
            $table->timestamps();

            $table->index(['user_id', 'año_academico']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docente_asignaturas');
    }
};
