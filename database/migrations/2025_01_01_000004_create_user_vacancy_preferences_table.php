<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_vacancy_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_list_id')->constrained('user_lists')->cascadeOnDelete();
            $table->foreignId('vacancy_id')->constrained('vacancies')->cascadeOnDelete();
            $table->integer('position')->default(0);
            $table->enum('status', ['selected', 'discarded', 'neutral'])->default('neutral');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_list_id', 'vacancy_id']);
            $table->index(['user_list_id', 'status', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_vacancy_preferences');
    }
};
