<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oposicion_temas', function (Blueprint $table) {
            // Imported from the official BOE temario (shows an "Oficial BOE" badge).
            $table->boolean('es_oficial')->default(false)->after('status');
            $table->foreignId('tema_oficial_id')->nullable()->after('es_oficial')
                ->constrained('temas_oficiales')->nullOnDelete();
            // Which esquema points the user has reviewed (array of point indices).
            $table->json('esquema_progreso')->nullable()->after('notas');
        });
    }

    public function down(): void
    {
        Schema::table('oposicion_temas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tema_oficial_id');
            $table->dropColumn(['es_oficial', 'esquema_progreso']);
        });
    }
};
