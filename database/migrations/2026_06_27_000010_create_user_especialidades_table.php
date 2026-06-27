<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_especialidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('specialty_id')->constrained('specialties')->cascadeOnDelete();
            $table->integer('posicion_bolsa')->nullable();
            $table->string('estado_bolsa', 20)->nullable();
            $table->smallInteger('anyo');
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('specialty_id');
            $table->unique(['user_id', 'specialty_id', 'anyo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_especialidades');
    }
};
