<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ccaa_id')->constrained('ccaas')->cascadeOnDelete();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 200);
            $table->string('tipo', 50);
            $table->string('localidad', 100);
            $table->string('provincia', 50);
            $table->string('direccion', 200)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('web', 200)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->jsonb('etapas')->nullable();
            $table->smallInteger('lineas')->nullable();
            $table->boolean('bilingue')->default(false);
            $table->boolean('datos_verificados')->default(false);
            $table->string('fuente', 50)->default('GVA');
            $table->timestamps();

            $table->index('ccaa_id');
            $table->index('provincia');
            $table->index('localidad');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centros');
    }
};
