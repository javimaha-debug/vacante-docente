<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temarios_oficiales', function (Blueprint $table) {
            $table->id();
            $table->enum('cuerpo', ['maestros', 'secundaria', 'fp', 'otros']);
            $table->string('especialidad_code');
            $table->string('especialidad_nombre');
            $table->string('comunidad_autonoma')->default('nacional');
            $table->string('source_url')->nullable();
            $table->string('source_order')->nullable(); // e.g. EDU/3136/2011
            $table->integer('total_temas')->default(0);
            $table->date('published_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['cuerpo', 'especialidad_code', 'comunidad_autonoma']);
            $table->index('especialidad_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temarios_oficiales');
    }
};
