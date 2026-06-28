<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vacancies', function (Blueprint $table) {
            // 'nueva' | 'modificada' | null — set when a re-import differs from
            // the previous listing of the same proceso.
            $table->string('cambio', 12)->nullable();
            $table->timestamp('cambio_en')->nullable();
        });

        // One row per import that summarises the diff vs the previous listing,
        // used for the "listado actualizado" banner and notifications.
        Schema::create('proceso_importaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proceso_id')->constrained('procesos')->cascadeOnDelete();
            $table->timestamp('importado_en');
            $table->integer('total')->default(0);
            $table->integer('nuevas')->default(0);
            $table->integer('modificadas')->default(0);
            $table->integer('eliminadas')->default(0);
            $table->boolean('es_primera')->default(false);
            $table->timestamps();

            $table->index(['proceso_id', 'importado_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proceso_importaciones');
        Schema::table('vacancies', function (Blueprint $table) {
            $table->dropColumn(['cambio', 'cambio_en']);
        });
    }
};
