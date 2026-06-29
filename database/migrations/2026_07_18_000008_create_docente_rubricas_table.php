<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docente_rubricas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asignatura_id')->nullable()->constrained('docente_asignaturas')->nullOnDelete();
            $table->string('titulo', 200);
            $table->text('descripcion')->nullable();
            $table->string('tipo_tarea', 60)->nullable();
            $table->enum('etapa', ['infantil', 'primaria', 'eso', 'bachillerato', 'fp', 'otros'])->nullable();
            $table->json('criterios'); // [{nombre, descripcion, niveles:[{nivel,descriptor,puntos}]}]
            $table->boolean('es_publica')->default(false);
            $table->unsignedInteger('veces_usada')->default(0);
            $table->timestamps();

            $table->index(['user_id']);
            $table->index(['es_publica', 'etapa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docente_rubricas');
    }
};
