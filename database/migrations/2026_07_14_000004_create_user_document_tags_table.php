<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_document_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('color', 9)->default('#0e6e5e');
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'name']);
        });

        Schema::create('user_document_tag_pivot', function (Blueprint $table) {
            $table->foreignId('document_id')->constrained('user_documents')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('user_document_tags')->cascadeOnDelete();
            $table->primary(['document_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_document_tag_pivot');
        Schema::dropIfExists('user_document_tags');
    }
};
