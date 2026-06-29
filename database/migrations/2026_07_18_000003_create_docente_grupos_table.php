<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docente_grupos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asignatura_id')->constrained('docente_asignaturas')->cascadeOnDelete();
            $table->string('nombre', 20); // '3ºA'
            $table->unsignedSmallInteger('num_alumnos')->nullable();
            $table->string('color', 7)->nullable(); // hex color
            $table->timestamps();

            $table->index(['user_id', 'asignatura_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docente_grupos');
    }
};
