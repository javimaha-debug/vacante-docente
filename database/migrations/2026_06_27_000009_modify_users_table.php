<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nombre_gva', 200)->nullable();
            // foreignId() creates unsignedBigInteger columns; FK constraints are
            // added separately below (SQLite cannot add FKs via ALTER).
            $table->foreignId('ccaa_id')->nullable();
            $table->foreignId('colectivo_id')->nullable();
            $table->string('direccion_origen', 300)->nullable();
            $table->decimal('lat_origen', 10, 8)->nullable();
            $table->decimal('lng_origen', 11, 8)->nullable();
            $table->jsonb('preferencias_filtro')->nullable();
            $table->boolean('notificaciones_email')->default(true);
            $table->string('avatar_url')->nullable();
            $table->string('locale', 10)->default('es');

            $table->index('ccaa_id');
            $table->index('colectivo_id');
        });

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('ccaa_id')->references('id')->on('ccaas')->nullOnDelete();
                $table->foreign('colectivo_id')->references('id')->on('colectivos')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['ccaa_id']);
                $table->dropForeign(['colectivo_id']);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['ccaa_id']);
            $table->dropIndex(['colectivo_id']);
            $table->dropColumn([
                'nombre_gva', 'ccaa_id', 'colectivo_id', 'direccion_origen',
                'lat_origen', 'lng_origen', 'preferencias_filtro',
                'notificaciones_email', 'avatar_url', 'locale',
            ]);
        });
    }
};
