<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('specialty_id')->constrained('specialties')->cascadeOnDelete();
            $table->foreignId('proceso_id')->nullable()->constrained('procesos')->nullOnDelete();
            $table->smallInteger('anyo');
            $table->integer('posicion_provisional')->nullable();
            $table->integer('posicion_definitiva')->nullable();
            $table->string('estado', 20)->nullable();
            $table->foreignId('centro_adjudicado_id')->nullable()->constrained('centros')->nullOnDelete();
            $table->string('lloc_adjudicado', 20)->nullable();
            $table->string('jornada_adjudicada', 50)->nullable();
            $table->date('fecha_adjudicacion')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('specialty_id');
            $table->index('proceso_id');
            $table->index('centro_adjudicado_id');
            $table->unique(['user_id', 'specialty_id', 'anyo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_historial');
    }
};
