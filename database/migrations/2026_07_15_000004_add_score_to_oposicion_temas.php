<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oposicion_temas', function (Blueprint $table) {
            $table->integer('score')->nullable();              // 0-100 mastery
            $table->integer('score_sessions')->default(0);     // evaluated sessions
            $table->timestamp('score_updated_at')->nullable();
            $table->json('score_breakdown')->nullable();       // {flashcards, simulacro, chat}
        });
    }

    public function down(): void
    {
        Schema::table('oposicion_temas', function (Blueprint $table) {
            $table->dropColumn(['score', 'score_sessions', 'score_updated_at', 'score_breakdown']);
        });
    }
};
