<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Pre-SaaS users (registered before the Fase 0 onboarding wizard shipped)
     * already have their data configured manually, so they should not be sent
     * through the wizard. Mark them as onboarding-complete.
     */
    public function up(): void
    {
        DB::table('users')
            ->where('created_at', '<', '2026-07-09')
            ->update(['onboarding_completed' => true]);
    }

    public function down(): void
    {
        // Irreversible by design: we cannot tell which users were flipped here
        // vs. those who genuinely completed onboarding afterwards.
    }
};
