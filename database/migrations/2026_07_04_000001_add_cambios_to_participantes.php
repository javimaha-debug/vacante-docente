<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participantes_proceso', function (Blueprint $table) {
            // 'nuevo' | 'modificado' | null — set when a re-import differs from
            // the previous participant listing of the same proceso.
            $table->string('cambio', 12)->nullable();
            $table->timestamp('cambio_en')->nullable();
        });

        // One row per participant-list import summarising the diff vs the
        // previous listing (banner + notifications).
        Schema::create('participante_importaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proceso_id')->constrained('procesos')->cascadeOnDelete();
            $table->timestamp('importado_en');
            $table->integer('total')->default(0);
            $table->integer('nuevos')->default(0);
            $table->integer('modificados')->default(0);
            $table->integer('eliminados')->default(0);
            $table->boolean('es_primera')->default(false);
            $table->timestamps();

            $table->index(['proceso_id', 'importado_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participante_importaciones');
        Schema::table('participantes_proceso', function (Blueprint $table) {
            $table->dropColumn(['cambio', 'cambio_en']);
        });
    }
};
