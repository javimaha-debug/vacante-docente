<?php

namespace Tests\Feature;

use App\Models\AdminNota;
use App\Models\Specialty;
use App\Models\Suscripcion;
use App\Models\User;
use App\Policies\FeaturePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SaasFoundationTest extends TestCase
{
    use RefreshDatabase;

    // --- Models / helpers / policy ---

    public function test_user_role_and_billing_fields_are_not_mass_assignable(): void
    {
        $user = User::create([
            'name' => 'Mass',
            'email' => 'mass@example.com',
            'password' => 'secret123',
            'role' => 'superadmin',
            'plan' => 'todo_en_uno',
            'plan_status' => 'active',
            'stripe_customer_id' => 'cus_hack',
        ]);

        $this->assertSame('user', $user->role);
        $this->assertSame('free', $user->plan);
        $this->assertSame('none', $user->plan_status);
        $this->assertNull($user->stripe_customer_id);
    }

    public function test_plan_and_role_helpers(): void
    {
        $free = User::factory()->create();
        $this->assertFalse($free->isPaid());
        $this->assertFalse($free->isAdmin());
        $this->assertFalse($free->isSuperAdmin());
        $this->assertSame('Gratis', $free->planLabel());

        $paid = User::factory()->create();
        $paid->forceFill(['plan' => 'interino', 'plan_status' => 'active'])->save();
        $this->assertTrue($paid->fresh()->isPaid());

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'superadmin'])->save();
        $this->assertTrue($admin->fresh()->isAdmin());
        $this->assertTrue($admin->fresh()->isSuperAdmin());
    }

    public function test_feature_policy_open_access_grants_every_feature(): void
    {
        // TEMPORARY open-access mode: gating is disabled, so every user has
        // every feature regardless of plan.
        $policy = app(FeaturePolicy::class);

        $free = User::factory()->create();
        $this->assertTrue($policy->hasFeature($free, 'explorador_basico'));
        $this->assertTrue($policy->hasFeature($free, 'filtros_avanzados'));
        $this->assertTrue($policy->hasFeature($free, 'banco_recursos'));

        foreach (FeaturePolicy::ALL_FEATURES as $feature) {
            $this->assertTrue($policy->hasFeature($free, $feature), "Feature {$feature} should be open.");
        }
    }

    public function test_feature_policy_resolution_logic_is_preserved(): void
    {
        // The plan → feature resolution (alias expansion) is kept intact so
        // gating can be re-enabled later, even though hasFeature() bypasses it.
        $policy = app(FeaturePolicy::class);

        $this->assertContains('explorador_basico', $policy->resolveFeatures('free'));
        $this->assertNotContains('filtros_avanzados', $policy->resolveFeatures('free'));
        $this->assertContains('filtros_avanzados', $policy->resolveFeatures('todo_en_uno'));
        $this->assertContains('banco_recursos', $policy->resolveFeatures('todo_en_uno'));
    }

    public function test_superadmin_has_every_feature(): void
    {
        $policy = app(FeaturePolicy::class);
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'superadmin'])->save();

        $this->assertTrue($policy->hasFeature($admin->fresh(), 'banco_recursos'));
    }

    // --- Profile payload exposes SaaS state ---

    public function test_profile_exposes_features_and_plan(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user/profile')
            ->assertOk()
            ->assertJsonPath('plan', 'free')
            ->assertJsonPath('features.explorador_basico', true)
            // Open access: gated features are also reported as granted.
            ->assertJsonPath('features.filtros_avanzados', true)
            ->assertJsonPath('is_impersonated', false);
    }

    // --- Middleware ---

    public function test_superadmin_routes_reject_regular_users(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/superadmin/dashboard')->assertForbidden();
    }

    public function test_superadmin_routes_allow_admins(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'superadmin'])->save();
        Sanctum::actingAs($admin->fresh());

        $this->getJson('/api/v1/superadmin/dashboard')
            ->assertOk()
            ->assertJsonStructure(['kpis' => ['usuarios_total'], 'por_plan', 'serie']);
    }

    // --- Usuarios management ---

    public function test_admin_can_change_user_plan_and_add_nota(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'superadmin'])->save();
        $target = User::factory()->create();
        Sanctum::actingAs($admin->fresh());

        $this->putJson("/api/v1/superadmin/usuarios/{$target->id}/plan", [
            'plan' => 'interino',
        ])->assertOk()->assertJsonPath('usuario.plan', 'interino');

        $this->assertSame('interino', $target->fresh()->plan);
        $this->assertDatabaseHas('admin_notas', ['user_id' => $target->id, 'tipo' => 'manual']);

        $this->postJson("/api/v1/superadmin/usuarios/{$target->id}/notas", [
            'nota' => 'Usuario contactado.',
        ])->assertCreated();
    }

    public function test_admin_can_suspend_and_reactivate_user(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'superadmin'])->save();
        $target = User::factory()->create();
        Sanctum::actingAs($admin->fresh());

        $this->postJson("/api/v1/superadmin/usuarios/{$target->id}/suspender", [
            'suspender' => true,
        ])->assertOk()->assertJsonPath('usuario.suspended', true);

        $this->assertNotNull($target->fresh()->suspended_at);

        $this->postJson("/api/v1/superadmin/usuarios/{$target->id}/suspender", [
            'suspender' => false,
        ])->assertOk()->assertJsonPath('usuario.suspended', false);
    }

    public function test_suspended_user_cannot_log_in(): void
    {
        $user = User::factory()->create(['email' => 'susp@example.com']);
        $user->forceFill(['suspended_at' => now()])->save();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'susp@example.com',
            'password' => 'password',
        ])->assertStatus(422);
    }

    // --- Impersonation ---

    public function test_admin_can_impersonate_and_profile_reports_it(): void
    {
        $admin = User::factory()->create(['name' => 'Boss']);
        $admin->forceFill(['role' => 'superadmin'])->save();
        $target = User::factory()->create();

        // Use a real bearer token (not Sanctum::actingAs, which would pin the
        // guard for the whole test and ignore the impersonation token).
        $adminToken = $admin->fresh()->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->postJson("/api/v1/superadmin/usuarios/{$target->id}/impersonate")
            ->assertOk()
            ->assertJsonStructure(['token', 'usuario']);

        $token = $response->json('token');

        // The auth guard caches the resolved user across requests in a single
        // test; reset it so the impersonation token re-resolves (each real HTTP
        // request is a fresh process, so production is unaffected).
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/user/profile')
            ->assertOk()
            ->assertJsonPath('is_impersonated', true)
            ->assertJsonPath('impersonated_by', 'Boss')
            ->assertJsonPath('id', $target->id);

        $this->assertDatabaseHas('admin_notas', ['user_id' => $target->id, 'tipo' => 'impersonacion']);
    }

    // --- Onboarding ---

    public function test_onboarding_saves_answers_and_marks_complete(): void
    {
        $specialty = Specialty::create([
            'code' => '590', 'codigo' => '590', 'name' => 'Matemáticas',
            'body' => 'Secundaria', 'education_level' => 'secundaria', 'cuerpo' => 'SECUNDARIA',
        ]);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/user/onboarding', [
            'modo_activo' => 'bolsa',
            'especialidades' => [$specialty->id],
            'nombre_gva' => 'GARCIA LOPEZ, ANA',
        ])->assertOk()->assertJsonPath('onboarding_completed', true);

        $fresh = $user->fresh();
        $this->assertTrue((bool) $fresh->onboarding_completed);
        $this->assertSame('bolsa', $fresh->modo_activo);
        $this->assertSame('GARCIA LOPEZ, ANA', $fresh->nombre_gva);
        $this->assertDatabaseHas('user_especialidades', [
            'user_id' => $user->id, 'specialty_id' => $specialty->id,
        ]);
    }

    public function test_onboarding_requires_at_least_one_specialty(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/v1/user/onboarding', [
            'modo_activo' => 'oposicion',
            'especialidades' => [],
        ])->assertStatus(422);
    }

    // --- Mode switching ---

    public function test_user_can_switch_active_mode(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/user/modo', ['modo_activo' => 'oposicion'])
            ->assertOk()
            ->assertJsonPath('modo_activo', 'oposicion');

        $this->assertSame('oposicion', $user->fresh()->modo_activo);
    }

    // --- Metrics command ---

    public function test_metricas_calcular_command_creates_snapshot(): void
    {
        User::factory()->count(3)->create();
        $paid = User::factory()->create();
        $paid->forceFill(['plan' => 'interino', 'plan_status' => 'active'])->save();

        $this->artisan('metricas:calcular')->assertSuccessful();

        $metrica = \App\Models\MetricaDiaria::first();
        $this->assertNotNull($metrica);
        $this->assertSame(now()->toDateString(), $metrica->fecha->toDateString());
        $this->assertSame(4, $metrica->usuarios_total);
        $this->assertSame(1, $metrica->usuarios_de_pago);
        $this->assertGreaterThan(0, (float) $metrica->mrr);
    }
}
