<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centro_horarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('centro_id')->constrained('centros')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->time('hora_entrada')->nullable();
            $table->time('hora_salida')->nullable();
            $table->time('hora_entrada_tarde')->nullable();
            $table->time('hora_salida_tarde')->nullable();
            $table->boolean('jornada_continua')->default(false);
            $table->string('dia_libre', 20)->nullable();
            $table->string('curso_escolar', 20);
            $table->smallInteger('validaciones')->default(1);
            $table->jsonb('validado_por')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index('centro_id');
            $table->index('user_id');
            $table->index(['centro_id', 'curso_escolar']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centro_horarios');
    }
};
