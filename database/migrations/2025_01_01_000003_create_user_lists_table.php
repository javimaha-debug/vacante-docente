<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_lists', function (Blueprint $table) {
            $table->id();
            $table->string('session_token', 64);
            $table->foreignId('specialty_id')->constrained()->cascadeOnDelete();
            $table->text('home_address')->nullable();
            $table->decimal('home_lat', 10, 7)->nullable();
            $table->decimal('home_lng', 10, 7)->nullable();
            $table->timestamps();

            // One list per session per specialty.
            $table->unique(['session_token', 'specialty_id']);
            $table->index('session_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_lists');
    }
};
