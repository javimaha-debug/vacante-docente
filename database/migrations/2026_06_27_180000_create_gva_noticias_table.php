<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gva_noticias', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 300);
            $table->string('url', 500)->unique();
            $table->date('fecha_publicacion')->nullable();
            $table->enum('tipo', ['RSS', 'PDF', 'WEB']);
            $table->text('resumen')->nullable();
            $table->jsonb('keywords_matched')->nullable();
            $table->boolean('notificado')->default(false);
            $table->timestamps();

            $table->index('fecha_publicacion');
            $table->index('tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gva_noticias');
    }
};
