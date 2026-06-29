<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 9)->nullable();          // hex e.g. #0e6e5e
            $table->foreignId('parent_id')->nullable()->constrained('user_folders')->nullOnDelete();
            // oposicion_temas does not exist yet — keep a nullable, indexed link.
            $table->unsignedBigInteger('tema_id')->nullable();
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'parent_id']);
            $table->index('tema_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_folders');
    }
};
