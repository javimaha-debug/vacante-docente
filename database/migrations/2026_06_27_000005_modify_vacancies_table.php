<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vacancies', function (Blueprint $table) {
            // foreignId() creates unsignedBigInteger columns; FK constraints are
            // added separately below (SQLite cannot add FKs via ALTER).
            $table->foreignId('proceso_id')->nullable();
            $table->foreignId('ccaa_id')->nullable();
            $table->integer('num_orden')->nullable();
            $table->string('codi_centre', 20)->nullable();
            $table->string('tipo_jornada', 50)->nullable();
            $table->boolean('requisito_linguistico')->nullable();
            $table->boolean('itinerante')->default(false);
            $table->text('observaciones')->nullable();
            $table->boolean('is_active')->default(true);
            // Note: `lloc`, `num`, `req_ling`, `observ`, `centro_codigo` already
            // exist on this table from the original migration and are preserved.

            $table->index('proceso_id');
            $table->index('ccaa_id');
        });

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('vacancies', function (Blueprint $table) {
                $table->foreign('proceso_id')->references('id')->on('procesos')->nullOnDelete();
                $table->foreign('ccaa_id')->references('id')->on('ccaas')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('vacancies', function (Blueprint $table) {
                $table->dropForeign(['proceso_id']);
                $table->dropForeign(['ccaa_id']);
            });
        }

        Schema::table('vacancies', function (Blueprint $table) {
            $table->dropIndex(['proceso_id']);
            $table->dropIndex(['ccaa_id']);
            $table->dropColumn([
                'proceso_id', 'ccaa_id', 'num_orden', 'codi_centre',
                'tipo_jornada', 'requisito_linguistico', 'itinerante',
                'observaciones', 'is_active',
            ]);
        });
    }
};
