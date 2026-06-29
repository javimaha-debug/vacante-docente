<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docente_recurso_valoraciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recurso_compartido_id')->constrained('docente_recursos_compartidos')->cascadeOnDelete();
            $table->unsignedTinyInteger('puntuacion'); // 1-5
            $table->text('comentario')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'recurso_compartido_id']); // one rating per user
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docente_recurso_valoraciones');
    }
};
