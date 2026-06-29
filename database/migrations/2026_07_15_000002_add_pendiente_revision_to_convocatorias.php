<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('convocatorias', function (Blueprint $table) {
            // Set by the monitor when it auto-detects a convocatoria; cleared once
            // a superadmin reviews/updates it.
            $table->boolean('pendiente_revision')->default(false)->after('estado');
            $table->index('pendiente_revision');
        });
    }

    public function down(): void
    {
        Schema::table('convocatorias', function (Blueprint $table) {
            $table->dropIndex(['pendiente_revision']);
            $table->dropColumn('pendiente_revision');
        });
    }
};
