<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('centros', function (Blueprint $table) {
            // Special characteristics sourced from the ANPE listings, e.g.
            // ['UECO', 'SINGULAR', 'JORNADA_CONTINUA', 'PENITENCIARI'].
            $table->jsonb('caracteristicas')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('centros', function (Blueprint $table) {
            $table->dropColumn('caracteristicas');
        });
    }
};
