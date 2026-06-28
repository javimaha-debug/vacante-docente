<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['user', 'admin', 'superadmin'])->default('user');
            $table->enum('plan', ['free', 'interino', 'opositor', 'docente_pro', 'todo_en_uno'])->default('free');
            $table->enum('plan_status', ['active', 'trialing', 'past_due', 'canceled', 'none'])->default('none');
            $table->timestamp('plan_expires_at')->nullable();
            $table->string('stripe_customer_id', 255)->nullable()->unique();
            $table->string('stripe_subscription_id', 255)->nullable();
            $table->enum('modo_activo', ['bolsa', 'oposicion', 'docente'])->default('bolsa');
            $table->json('ccaa_preferidas')->nullable();
            $table->boolean('onboarding_completed')->default(false);
            $table->timestamp('last_active_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role', 'plan', 'plan_status', 'plan_expires_at', 'stripe_customer_id',
                'stripe_subscription_id', 'modo_activo', 'ccaa_preferidas',
                'onboarding_completed', 'last_active_at', 'trial_ends_at',
            ]);
        });
    }
};
