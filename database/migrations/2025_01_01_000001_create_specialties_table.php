<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specialties', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10);
            $table->string('name', 200);
            $table->string('body', 200);
            $table->enum('education_level', ['maestros', 'secundaria', 'fp']);
            $table->timestamps();

            $table->unique(['code', 'education_level']);
            $table->index('education_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specialties');
    }
};
