<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Weekly ("contínues") adjudications: one row per (tanda date × person ×
        // specialty). Every weekly batch is kept, so a teacher's full weekly
        // history is preserved (option B).
        Schema::create('adjudicaciones_continuas', function (Blueprint $table) {
            $table->id();
            $table->string('curso', 12)->nullable();   // e.g. 2025-2026
            $table->date('fecha');                       // tanda date
            $table->string('cuerpo', 20)->nullable();    // SECUNDARIA | MAESTROS
            $table->string('nombre_gva', 200);
            $table->string('especialidad_codigo', 10)->nullable();
            $table->integer('posicion')->nullable();
            $table->string('estado', 20)->nullable();    // Activat | Desactivat | Adjudicat
            $table->string('lloc_adjudicado', 20)->nullable();
            $table->string('centro_codigo', 20)->nullable();
            $table->string('centro_nombre', 300)->nullable();
            $table->string('localitat', 120)->nullable();
            $table->string('jornada', 60)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('fecha');
            $table->index('nombre_gva');
            $table->index(['user_id', 'fecha']);
            $table->index(['fecha', 'cuerpo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adjudicaciones_continuas');
    }
};
