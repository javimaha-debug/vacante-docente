<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_position_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('especialidad_code', 50);
            $table->unsignedInteger('posicion');
            $table->unsignedInteger('total')->nullable();
            $table->timestamp('recorded_at');
            $table->index(['user_id', 'especialidad_code']);
            $table->index(['user_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_position_history');
    }
};
