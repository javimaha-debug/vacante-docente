<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('b2_user_profile', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->integer('reading_score')->default(0);
            $table->integer('grammar_score')->default(0);
            $table->integer('writing_score')->default(0);
            $table->integer('listening_score')->default(0);
            $table->integer('speaking_score')->default(0);
            $table->integer('overall_score')->default(0);
            $table->string('diagnostic_band')->nullable();
            $table->timestamp('diagnostic_completed_at')->nullable();
            $table->timestamp('last_practiced_at')->nullable();
            $table->timestamps();
            $table->index('user_id');
        });

        Schema::create('b2_exercises', function (Blueprint $table) {
            $table->id();
            $table->enum('skill', ['reading', 'grammar', 'writing', 'listening', 'speaking']);
            $table->integer('difficulty');
            $table->string('type');
            $table->text('question_text');
            $table->json('options')->nullable();
            $table->text('user_input_instruction')->nullable();
            $table->text('correct_answer');
            $table->text('explanation')->nullable();
            $table->integer('points_reward');
            $table->string('category')->nullable();
            $table->string('source')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('skill');
            $table->index('difficulty');
            $table->index('type');
        });

        Schema::create('b2_exercise_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('exercise_id')->constrained('b2_exercises')->onDelete('cascade');
            $table->enum('skill', ['reading', 'grammar', 'writing', 'listening', 'speaking']);
            $table->text('user_answer');
            $table->boolean('is_correct');
            $table->integer('points_earned');
            $table->text('feedback_ia')->nullable();
            $table->integer('time_spent_seconds')->nullable();
            $table->float('score_percentage')->nullable();
            $table->timestamps();
            $table->index('user_id');
            $table->index('skill');
            $table->index('is_correct');
        });

        Schema::create('b2_skill_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('skill', ['reading', 'grammar', 'writing', 'listening', 'speaking']);
            $table->integer('current_score')->default(0);
            $table->integer('exercises_completed')->default(0);
            $table->integer('exercises_correct')->default(0);
            $table->float('accuracy_percentage')->default(0);
            $table->enum('mastery_level', ['beginner', 'intermediate', 'advanced', 'mastered'])->default('beginner');
            $table->timestamp('last_practiced_at')->nullable();
            $table->timestamps();
            $table->index('user_id');
            $table->index('skill');
            $table->unique(['user_id', 'skill']);
        });

        Schema::create('b2_diagnostic_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->integer('reading_score');
            $table->integer('grammar_score');
            $table->integer('listening_score');
            $table->integer('writing_score');
            $table->integer('speaking_score');
            $table->integer('total_score');
            $table->string('band_assigned');
            $table->json('weaknesses')->nullable();
            $table->json('recommendations')->nullable();
            $table->timestamp('completed_at');
            $table->timestamps();
            $table->index('user_id');
        });

        Schema::create('b2_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('badge_id');
            $table->string('badge_name');
            $table->text('badge_description');
            $table->string('icon_emoji');
            $table->timestamp('unlocked_at');
            $table->timestamps();
            $table->index('user_id');
            $table->unique(['user_id', 'badge_id']);
        });

        Schema::create('b2_user_streaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->integer('current_streak_days')->default(0);
            $table->integer('longest_streak_days')->default(0);
            $table->date('last_practiced_date')->nullable();
            $table->timestamp('streak_reset_at')->nullable();
            $table->timestamps();
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('b2_user_streaks');
        Schema::dropIfExists('b2_achievements');
        Schema::dropIfExists('b2_diagnostic_results');
        Schema::dropIfExists('b2_skill_progress');
        Schema::dropIfExists('b2_exercise_history');
        Schema::dropIfExists('b2_exercises');
        Schema::dropIfExists('b2_user_profile');
    }
};
