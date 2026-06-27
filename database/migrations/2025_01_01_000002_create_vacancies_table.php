<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('specialty_id')->constrained()->cascadeOnDelete();
            $table->integer('num');
            $table->enum('provincia', ['Alacant', 'Castelló', 'València']);
            $table->string('localidad', 200);
            $table->string('centro_codigo', 20);
            $table->string('centro_nombre', 300);
            $table->enum('tipo_centro', ['Secundaria', 'Primaria/Infantil', 'Otro']);
            $table->string('lloc', 20);
            $table->boolean('req_ling')->default(false);
            $table->text('observ')->nullable();
            $table->json('observ_tags')->nullable();
            $table->integer('year')->default(2025);
            $table->timestamps();

            $table->index(['specialty_id', 'year']);
            $table->index('provincia');
            $table->index('tipo_centro');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacancies');
    }
};
