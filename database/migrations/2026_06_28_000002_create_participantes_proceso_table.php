<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participantes_proceso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proceso_id')->constrained('procesos')->cascadeOnDelete();
            $table->integer('posicion')->nullable();
            $table->string('nombre_gva', 200);
            $table->string('estado', 20)->nullable();
            $table->string('lloc_adjudicado', 20)->nullable();
            $table->string('centro_nombre', 200)->nullable();
            $table->string('localitat', 100)->nullable();
            $table->string('especialidad_codigo', 10)->nullable();
            $table->string('jornada', 50)->nullable();
            $table->timestamps();

            $table->index('proceso_id');
            $table->index('nombre_gva');
            $table->index(['proceso_id', 'posicion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participantes_proceso');
    }
};
