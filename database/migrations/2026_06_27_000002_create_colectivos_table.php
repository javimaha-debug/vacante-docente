<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('colectivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ccaa_id')->constrained('ccaas')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->string('body', 50);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('ccaa_id');
            $table->index(['ccaa_id', 'code', 'body']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colectivos');
    }
};
