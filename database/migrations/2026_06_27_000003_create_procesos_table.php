<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procesos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ccaa_id')->constrained('ccaas')->cascadeOnDelete();
            $table->foreignId('colectivo_id')->constrained('colectivos')->cascadeOnDelete();
            $table->smallInteger('anyo');
            $table->string('curso', 20);
            $table->string('nombre', 200);
            $table->enum('estado', ['pendiente', 'publicado', 'cerrado'])->default('pendiente');
            $table->date('fecha_publicacion_vacantes')->nullable();
            $table->date('fecha_inicio_peticiones')->nullable();
            $table->date('fecha_fin_peticiones')->nullable();
            $table->date('fecha_adjudicacion')->nullable();
            $table->string('pdf_vacantes_url')->nullable();
            $table->string('pdf_participantes_url')->nullable();
            $table->string('pdf_adjudicacion_url')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index('ccaa_id');
            $table->index('colectivo_id');
            $table->index(['ccaa_id', 'anyo', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procesos');
    }
};
