<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('normativa_documentos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->enum('categoria', ['ley_organica', 'decreto', 'orden', 'resolucion', 'instrucciones', 'otro']);
            $table->string('comunidad_autonoma');
            $table->string('especialidad_code')->nullable(); // null = todas
            $table->string('cuerpo')->nullable();             // null = todos
            $table->string('url_oficial')->nullable();
            $table->string('pdf_path')->nullable();
            $table->date('fecha_publicacion')->nullable();
            $table->boolean('vigente')->default(true);
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('categoria');
            $table->index('comunidad_autonoma');
            $table->index('especialidad_code');
            $table->index('vigente');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('normativa_documentos');
    }
};
