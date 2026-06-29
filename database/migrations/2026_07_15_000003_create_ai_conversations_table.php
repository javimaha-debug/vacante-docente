<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('mode', 20)->default('chat');           // chat | flashcards | simulacro | simulador_oral
            $table->string('context_type', 20)->default('free');   // free | temario | document
            $table->string('especialidad_code')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->string('role', 12);                            // user | assistant
            $table->text('content');
            $table->json('chunks_used')->nullable();               // array of chunk ids
            $table->integer('tokens_input')->nullable();
            $table->integer('tokens_output')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('conversation_id');
        });

        Schema::create('ai_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->integer('messages_count')->default(0);
            $table->bigInteger('tokens_input')->default(0);
            $table->bigInteger('tokens_output')->default(0);
            $table->integer('voyage_calls')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
