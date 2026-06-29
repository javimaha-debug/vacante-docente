<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tracks the last run of each automated sync/monitor (normativa BOE/DOGV,
        // convocatorias monitor) so the superadmin panel can show "last synced".
        Schema::create('sync_states', function (Blueprint $table) {
            $table->id();
            $table->string('clave')->unique(); // normativa_boe | normativa_dogv | convocatorias_monitor
            $table->timestamp('last_run_at')->nullable();
            $table->json('resumen')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_states');
    }
};
