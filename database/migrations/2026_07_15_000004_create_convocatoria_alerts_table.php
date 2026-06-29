<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convocatoria_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('convocatoria_id')->constrained('convocatorias')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'convocatoria_id']);
            $table->index('convocatoria_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convocatoria_alerts');
    }
};
