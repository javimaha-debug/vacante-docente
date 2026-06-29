<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docente_situaciones_aprendizaje', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asignatura_id')->nullable()->constrained('docente_asignaturas')->nullOnDelete();
            $table->string('titulo', 200);
            $table->text('descripcion')->nullable();
            $table->text('contexto')->nullable();
            $table->json('competencias_clave')->nullable();
            $table->json('competencias_especificas')->nullable();
            $table->json('saberes_basicos')->nullable();
            $table->json('actividades')->nullable(); // [{titulo,descripcion,tiempo_min,agrupamiento}]
            $table->json('criterios_evaluacion')->nullable();
            $table->enum('etapa', ['infantil', 'primaria', 'eso', 'bachillerato', 'fp', 'otros'])->nullable();
            $table->string('curso', 30)->nullable();
            $table->string('asignatura', 100)->nullable();
            $table->boolean('es_publica')->default(false);
            $table->unsignedInteger('veces_usada')->default(0);
            $table->timestamps();

            $table->index(['user_id']);
            $table->index(['es_publica', 'etapa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docente_situaciones_aprendizaje');
    }
};
