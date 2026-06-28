<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gva_noticias', function (Blueprint $table) {
            // Auto-import bookkeeping for detected listing PDFs.
            $table->timestamp('importado_en')->nullable();
            $table->string('import_estado', 20)->nullable(); // ok | sin_proceso | error
            $table->text('import_resumen')->nullable();
            $table->foreignId('proceso_id')->nullable()->constrained('procesos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gva_noticias', function (Blueprint $table) {
            $table->dropConstrainedForeignId('proceso_id');
            $table->dropColumn(['importado_en', 'import_estado', 'import_resumen']);
        });
    }
};
