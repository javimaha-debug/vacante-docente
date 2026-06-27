<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centro_valoraciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('centro_id')->constrained('centros')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->tinyInteger('puntuacion');
            $table->tinyInteger('ambiente_trabajo')->nullable();
            $table->tinyInteger('equipo_directivo')->nullable();
            $table->tinyInteger('instalaciones')->nullable();
            $table->text('comentario')->nullable();
            $table->boolean('es_anonima')->default(true);
            $table->string('curso_escolar', 20);
            $table->timestamps();
            $table->softDeletes();

            $table->index('centro_id');
            $table->index('user_id');
            $table->unique(['centro_id', 'user_id', 'curso_escolar']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centro_valoraciones');
    }
};
