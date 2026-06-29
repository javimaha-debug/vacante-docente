<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oposicion_especialidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('especialidad_code');
            $table->enum('cuerpo', ['maestros', 'secundaria', 'fp', 'otros']);
            $table->string('comunidad_autonoma')->default('valenciana');
            $table->timestamps();

            $table->index('user_id');
            $table->unique(['user_id', 'especialidad_code', 'cuerpo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oposicion_especialidades');
    }
};
