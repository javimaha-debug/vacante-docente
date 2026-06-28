<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Classify each detected listing by the GVA process it belongs to:
     * 'inicio' (adjudicación de inicio de curso), 'continua' (semanales) or
     * 'otro' (resolución / histórico / sin clasificar).
     */
    public function up(): void
    {
        Schema::table('gva_noticias', function (Blueprint $table) {
            $table->string('categoria', 20)->nullable()->after('tipo');
        });
    }

    public function down(): void
    {
        Schema::table('gva_noticias', function (Blueprint $table) {
            $table->dropColumn('categoria');
        });
    }
};
