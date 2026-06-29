<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docente_unidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programacion_id')->constrained('docente_programaciones')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->string('titulo', 200);
            $table->enum('tipo', ['unidad_didactica', 'situacion_aprendizaje', 'proyecto'])->default('unidad_didactica');
            $table->text('descripcion')->nullable();
            $table->json('competencias')->nullable();
            $table->json('criterios_evaluacion')->nullable();
            $table->unsignedSmallInteger('num_sesiones_previstas')->default(1);
            $table->enum('trimestre', ['primero', 'segundo', 'tercero'])->default('primero');
            $table->foreignId('tema_oficial_id')->nullable()->constrained('temas_oficiales')->nullOnDelete();
            $table->timestamps();

            $table->index(['programacion_id', 'numero']);
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docente_unidades');
    }
};
