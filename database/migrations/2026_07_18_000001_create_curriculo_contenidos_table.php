<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curriculo_contenidos', function (Blueprint $table) {
            $table->id();
            $table->string('etapa', 20); // primaria, eso, bachillerato, fp
            $table->string('asignatura', 100);
            $table->string('curso', 30); // '1º ESO', '2º Bachillerato'
            $table->string('bloque', 100)->nullable();
            $table->text('contenido');
            $table->json('competencias_clave')->nullable();
            $table->json('criterios_evaluacion')->nullable();
            $table->string('fuente', 20)->default('boe'); // boe | dogv
            $table->string('comunidad_autonoma', 30)->nullable();
            $table->string('real_decreto', 50)->nullable(); // RD 217/2022
            $table->timestamps();

            $table->index(['etapa', 'asignatura', 'curso']);
            $table->index(['fuente', 'comunidad_autonoma']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculo_contenidos');
    }
};
