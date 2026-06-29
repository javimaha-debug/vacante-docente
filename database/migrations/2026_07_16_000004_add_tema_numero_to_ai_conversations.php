<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            // When studying a specific official tema, the assistant loads its
            // esquema + keywords into the system prompt (Sprint C, Part 8).
            $table->integer('tema_numero')->nullable()->after('especialidad_code');
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropColumn('tema_numero');
        });
    }
};
