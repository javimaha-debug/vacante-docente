<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_lists', function (Blueprint $table) {
            // foreignId() creates the column; FK constraints are added below
            // (SQLite cannot add FKs via ALTER).
            $table->foreignId('user_id')->nullable();
            $table->foreignId('proceso_id')->nullable();

            $table->index('user_id');
            $table->index('proceso_id');
            $table->index(['user_id', 'proceso_id']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('user_lists', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('proceso_id')->references('id')->on('procesos')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('user_lists', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropForeign(['proceso_id']);
            });
        }

        Schema::table('user_lists', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['proceso_id']);
            $table->dropIndex(['user_id', 'proceso_id']);
            $table->dropColumn(['user_id', 'proceso_id']);
        });
    }
};
