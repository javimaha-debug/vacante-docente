<?php

use App\Support\NameMatch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Accent/case-folded copy of nombre_gva so name searches are accent-insensitive
 * and index-backed (SQL LOWER() does not fold accents on SQLite/MySQL). Both
 * listing tables get the column; existing rows are backfilled.
 */
return new class extends Migration
{
    private const TABLES = ['participantes_proceso', 'adjudicaciones_continuas'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('nombre_normalizado')->nullable()->after('nombre_gva')->index();
            });

            // Backfill existing rows in chunks.
            DB::table($table)->select('id', 'nombre_gva')->orderBy('id')->chunkById(500, function ($rows) use ($table) {
                foreach ($rows as $row) {
                    DB::table($table)->where('id', $row->id)
                        ->update(['nombre_normalizado' => NameMatch::fold($row->nombre_gva)]);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('nombre_normalizado');
            });
        }
    }
};
