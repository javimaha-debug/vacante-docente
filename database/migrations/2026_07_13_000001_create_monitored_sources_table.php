<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pages the document monitor scans for new official listings/notices.
        Schema::create('monitored_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url', 500);
            $table->string('type', 20)->default('gva');  // gva | sindicato | dogv
            $table->string('specialty', 100)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->index(['active', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitored_sources');
    }
};
