<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tablon_contactos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anuncio_id')->constrained('tablon_anuncios')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('mensaje');
            $table->boolean('email_enviado')->default(false);
            $table->boolean('leido')->default(false);
            $table->timestamps();

            $table->index('anuncio_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tablon_contactos');
    }
};
