<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The official postal address from the GVA directory, kept distinct from
     * the existing `direccion` (which may come from other sources). web,
     * telefono and email columns already exist on the table.
     */
    public function up(): void
    {
        Schema::table('centros', function (Blueprint $table) {
            $table->string('direccion_oficial', 255)->nullable()->after('direccion');
        });
    }

    public function down(): void
    {
        Schema::table('centros', function (Blueprint $table) {
            $table->dropColumn('direccion_oficial');
        });
    }
};
