<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docente_sesiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grupo_id')->constrained('docente_grupos')->cascadeOnDelete();
            $table->foreignId('unidad_id')->nullable()->constrained('docente_unidades')->nullOnDelete();
            $table->date('fecha');
            $table->string('titulo_planificado', 200);
            $table->text('contenido_real')->nullable();
            $table->boolean('impartida')->default(false);
            $table->timestamp('impartida_at')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'fecha']);
            $table->index(['grupo_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docente_sesiones');
    }
};
