<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docente_examenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asignatura_id')->nullable()->constrained('docente_asignaturas')->nullOnDelete();
            $table->foreignId('unidad_id')->nullable()->constrained('docente_unidades')->nullOnDelete();
            $table->string('titulo', 200);
            $table->enum('tipo', ['test', 'desarrollo', 'mixto', 'oral']);
            $table->unsignedSmallInteger('tiempo_minutos')->nullable();
            $table->text('instrucciones')->nullable();
            $table->json('preguntas'); // [{tipo,enunciado,opciones[],respuesta_correcta,puntos}]
            $table->timestamps();

            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docente_examenes');
    }
};
