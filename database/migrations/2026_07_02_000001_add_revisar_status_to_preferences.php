<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The status was an enum (driving a CHECK constraint on PostgreSQL),
        // so a new value like "revisar" would be rejected. Widen to a string;
        // the allowed set is enforced at the request-validation layer.
        Schema::table('user_vacancy_preferences', function (Blueprint $table) {
            $table->string('status', 20)->default('neutral')->change();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE user_vacancy_preferences DROP CONSTRAINT IF EXISTS user_vacancy_preferences_status_check');
        }
    }

    public function down(): void
    {
        // Irreversible: narrowing again would reject "revisar" rows.
    }
};
