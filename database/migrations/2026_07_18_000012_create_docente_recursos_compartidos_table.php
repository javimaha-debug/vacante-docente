<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docente_recursos_compartidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // autor
            $table->enum('tipo', ['rubrica', 'situacion_aprendizaje', 'actividad', 'examen']);
            $table->unsignedBigInteger('recurso_id');
            $table->boolean('moderado')->default(false); // must be approved by superadmin
            $table->decimal('valoracion_media', 3, 2)->default(0);
            $table->unsignedInteger('num_valoraciones')->default(0);
            $table->unsignedInteger('num_descargas')->default(0);
            $table->timestamps();

            $table->index(['tipo', 'moderado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docente_recursos_compartidos');
    }
};
