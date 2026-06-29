<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temas_oficiales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('temario_id')->constrained('temarios_oficiales')->cascadeOnDelete();
            $table->integer('numero');
            $table->text('titulo');
            $table->json('esquema')->nullable();       // [{punto, subpuntos[]}]
            $table->json('bibliografia')->nullable();  // [{titulo, autor, año, tipo, url}]
            $table->json('keywords')->nullable();      // [términos]
            $table->integer('tiempo_estimado_minutos')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['temario_id', 'numero']);
            $table->index('temario_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temas_oficiales');
    }
};
