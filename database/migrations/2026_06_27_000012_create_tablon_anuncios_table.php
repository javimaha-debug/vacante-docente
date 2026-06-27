<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tablon_anuncios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ccaa_id')->constrained('ccaas')->cascadeOnDelete();
            $table->enum('categoria', ['coche', 'alojamiento', 'centro', 'general']);
            $table->string('titulo', 200);
            $table->text('contenido');
            $table->string('localidad_origen', 100)->nullable();
            $table->string('localidad_destino', 100)->nullable();
            $table->foreignId('centro_id')->nullable()->constrained('centros')->nullOnDelete();
            $table->foreignId('specialty_id')->nullable()->constrained('specialties')->nullOnDelete();
            $table->string('contacto_email', 100)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('ccaa_id');
            $table->index('centro_id');
            $table->index('specialty_id');
            $table->index(['ccaa_id', 'categoria', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tablon_anuncios');
    }
};
