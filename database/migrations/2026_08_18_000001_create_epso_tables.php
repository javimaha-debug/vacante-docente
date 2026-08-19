<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('test_razonamiento', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['verbal', 'numerico', 'abstracto']);
            $table->text('pregunta');
            $table->json('opciones');
            $table->integer('respuesta_correcta');
            $table->integer('tiempo_esperado_segundos')->default(90);
            $table->text('explicacion');
            $table->integer('dificultad')->default(2);
            $table->string('tipo_error')->nullable();
            $table->timestamps();

            $table->index('tipo');
            $table->index('dificultad');
        });

        Schema::create('sesion_test', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->enum('tipo_razonamiento', ['verbal', 'numerico', 'abstracto']);
            $table->timestamp('fecha_inicio');
            $table->timestamp('fecha_fin')->nullable();
            $table->integer('preguntas_respondidas')->default(0);
            $table->integer('correctas')->default(0);
            $table->integer('tiempo_total_segundos')->default(0);
            $table->float('tiempo_promedio_pregunta')->default(0);
            $table->json('errores')->nullable();
            $table->float('score')->default(0);
            $table->timestamps();

            $table->index('user_id');
            $table->index('tipo_razonamiento');
            $table->index('fecha_inicio');
        });

        Schema::create('flashcard_ue', function (Blueprint $table) {
            $table->id();
            $table->enum('categoria', ['instituciones', 'historia', 'politicas', 'derecho', 'otras']);
            $table->text('frente');
            $table->text('reverso');
            $table->integer('dificultad')->default(2);
            $table->integer('repeticiones')->default(0);
            $table->timestamp('fecha_proxima_repaso')->nullable();
            $table->timestamps();

            $table->index('categoria');
            $table->index('dificultad');
        });

        Schema::create('sesion_aprendizaje', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->date('fecha');
            $table->enum('tipo_actividad', ['tests', 'flashcards', 'escritura']);
            $table->integer('duracion_minutos')->default(0);
            $table->boolean('completada')->default(false);
            $table->text('notas_ia')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesion_aprendizaje');
        Schema::dropIfExists('flashcard_ue');
        Schema::dropIfExists('sesion_test');
        Schema::dropIfExists('test_razonamiento');
    }
};
