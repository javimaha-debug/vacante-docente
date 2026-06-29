<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oposicion_temas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('especialidad_code');
            $table->integer('numero');
            $table->string('titulo');
            $table->enum('status', ['pendiente', 'en_progreso', 'dominado'])->default('pendiente');
            $table->text('notas')->nullable();
            $table->timestamp('last_studied_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'especialidad_code']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oposicion_temas');
    }
};
