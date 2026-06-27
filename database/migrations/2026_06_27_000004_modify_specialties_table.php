<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('specialties', function (Blueprint $table) {
            // foreignId() creates an unsignedBigInteger column; the FK constraint
            // is added separately below (SQLite cannot add FKs via ALTER).
            $table->foreignId('ccaa_id')->nullable()->default(null);
            $table->string('codigo', 10)->nullable();
            $table->string('cuerpo', 50)->nullable();
            $table->boolean('is_active')->default(true);

            $table->index('ccaa_id');
        });

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('specialties', function (Blueprint $table) {
                $table->foreign('ccaa_id')->references('id')->on('ccaas')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('specialties', function (Blueprint $table) {
                $table->dropForeign(['ccaa_id']);
            });
        }

        Schema::table('specialties', function (Blueprint $table) {
            $table->dropIndex(['ccaa_id']);
            $table->dropColumn(['ccaa_id', 'codigo', 'cuerpo', 'is_active']);
        });
    }
};
