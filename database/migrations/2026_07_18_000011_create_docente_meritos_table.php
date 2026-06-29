<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docente_meritos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('tipo', ['formacion', 'publicacion', 'cargo', 'actividad_complementaria', 'otro']);
            $table->string('titulo', 200);
            $table->string('organismo', 150)->nullable();
            $table->unsignedSmallInteger('horas')->nullable();
            $table->decimal('creditos_ects', 5, 2)->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->decimal('puntos_calculados', 6, 3)->nullable();
            $table->foreignId('document_id')->nullable()->constrained('user_documents')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docente_meritos');
    }
};
