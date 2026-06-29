<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('normativa_documentos', function (Blueprint $table) {
            // Where the document came from: boe | dogv | manual.
            $table->string('fuente')->default('manual')->after('vigente');
            // Document language for DOGV (castellano | valenciano); null = unknown.
            $table->string('idioma')->nullable()->after('fuente');

            $table->index('fuente');
        });
    }

    public function down(): void
    {
        Schema::table('normativa_documentos', function (Blueprint $table) {
            $table->dropIndex(['fuente']);
            $table->dropColumn(['fuente', 'idioma']);
        });
    }
};
