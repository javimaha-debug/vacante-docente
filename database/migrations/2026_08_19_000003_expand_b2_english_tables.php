<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Chat IA history
        Schema::create('b2_chat_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('exercise_history_id')->nullable()->constrained('b2_exercise_history')->onDelete('set null');
            $table->string('skill', 20)->nullable();
            $table->enum('role', ['user', 'assistant']);
            $table->text('content');
            $table->string('context_type', 50)->nullable(); // why_failed, general, help
            $table->timestamps();

            $table->index(['user_id', 'skill']);
            $table->index('exercise_history_id');
        });

        // B2 resources (links, videos, podcasts)
        Schema::create('b2_resources', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30); // video, article, podcast, reference, interactive
            $table->string('title');
            $table->text('description');
            $table->string('url', 500);
            $table->string('level', 10)->default('B2');
            $table->string('category', 50)->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->string('source', 100)->nullable();
            $table->string('icon_emoji', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'category']);
        });

        // User favorited resources
        Schema::create('b2_user_resources_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('resource_id')->constrained('b2_resources')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['user_id', 'resource_id']);
        });

        // Mock exams
        Schema::create('b2_mock_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('status', 20)->default('in_progress'); // in_progress, completed, abandoned
            $table->integer('total_duration_minutes')->default(150);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            // Section scores (reading, writing, listening)
            $table->integer('reading_score')->nullable();
            $table->integer('writing_score')->nullable();
            $table->integer('listening_score')->nullable();
            $table->integer('total_score')->nullable(); // Cambridge 0-200 scale
            $table->string('grade', 5)->nullable(); // A, B, C, D, U
            $table->json('section_answers')->nullable();
            $table->json('section_results')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        // Analytics cache per user (updated on exercise completion)
        Schema::create('b2_user_analytics_cache', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->unique();
            // Error patterns
            $table->json('top_error_categories')->nullable(); // [{category, count, skill}]
            $table->json('weak_grammar_points')->nullable();
            $table->json('weak_skills')->nullable();
            // Trend data
            $table->json('score_trend_7d')->nullable(); // [{date, skill, score}]
            $table->json('accuracy_by_skill')->nullable();
            $table->json('time_per_exercise')->nullable();
            // Study habits
            $table->integer('avg_session_minutes')->nullable();
            $table->integer('exercises_per_day_avg')->nullable();
            $table->timestamp('last_computed_at')->nullable();
            $table->timestamps();
        });

        // Alter b2_exercise_history to add AI evaluation columns
        Schema::table('b2_exercise_history', function (Blueprint $table) {
            $table->json('ai_evaluation_scores')->nullable()->after('feedback_ia');
            $table->text('ai_evaluation_feedback')->nullable()->after('ai_evaluation_scores');
            $table->boolean('ai_evaluation_done')->default(false)->after('ai_evaluation_feedback');
            $table->string('ai_evaluation_model', 50)->nullable()->after('ai_evaluation_done');
            $table->integer('ai_tokens_used')->nullable()->after('ai_evaluation_model');
        });
    }

    public function down(): void
    {
        // Drop AI columns first
        Schema::table('b2_exercise_history', function (Blueprint $table) {
            $table->dropColumn([
                'ai_evaluation_scores',
                'ai_evaluation_feedback',
                'ai_evaluation_done',
                'ai_evaluation_model',
                'ai_tokens_used',
            ]);
        });

        Schema::dropIfExists('b2_user_analytics_cache');
        Schema::dropIfExists('b2_mock_exams');
        Schema::dropIfExists('b2_user_resources_favorites');
        Schema::dropIfExists('b2_resources');
        Schema::dropIfExists('b2_chat_history');
    }
};
