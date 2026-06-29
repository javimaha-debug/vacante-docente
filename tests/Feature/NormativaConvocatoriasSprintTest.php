<?php

namespace Tests\Feature;

use App\Models\Convocatoria;
use App\Models\ConvocatoriaAlert;
use App\Models\NormativaDocumento;
use App\Models\SyncState;
use App\Models\User;
use App\Notifications\ConvocatoriasDetectadas;
use App\Notifications\ConvocatoriaStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NormativaConvocatoriasSprintTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_boe_creates_records_and_is_idempotent(): void
    {
        User::factory()->create(['role' => 'superadmin']);

        Http::fake([
            '*' => Http::response([
                'results' => [
                    [
                        'titulo' => 'Ley Orgánica 3/2020 (LOMLOE)',
                        'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2020-17264',
                        'fecha_publicacion' => '2020-12-30',
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('normativa:sync-boe')->assertSuccessful();

        $doc = NormativaDocumento::first();
        $this->assertNotNull($doc);
        $this->assertSame('boe', $doc->fuente);
        $this->assertSame('nacional', $doc->comunidad_autonoma);
        $this->assertSame('ley_organica', $doc->categoria);

        // Re-running does not duplicate (dedup by url_oficial).
        $this->artisan('normativa:sync-boe')->assertSuccessful();
        $this->assertSame(1, NormativaDocumento::count());

        $state = SyncState::where('clave', 'normativa_boe')->first();
        $this->assertNotNull($state->last_run_at);
    }

    public function test_sync_dogv_detects_language_and_source(): void
    {
        Storage::fake('public');
        User::factory()->create(['role' => 'superadmin']);

        $html = '<html><body>'
            .'<a href="/docs/decret-curriculum-eso.pdf">Decret pel qual s\'estableix el currículum d\'ESO</a>'
            .'<a href="/algo.pdf">Documento sin relación</a>'
            .'</body></html>';

        Http::fake(['*' => Http::response($html, 200)]);

        $this->artisan('normativa:sync-dogv')->assertSuccessful();

        $doc = NormativaDocumento::where('fuente', 'dogv')->first();
        $this->assertNotNull($doc);
        $this->assertSame('valenciana', $doc->comunidad_autonoma);
        $this->assertSame('valenciano', $doc->idioma);
    }

    public function test_convocatorias_monitor_creates_and_notifies_superadmin(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'superadmin']);

        Http::fake([
            'www.boe.es/*' => Http::response([
                'results' => [[
                    'titulo' => 'Convocatoria oposiciones ingreso cuerpos docentes 2026',
                    'url' => 'https://www.boe.es/diario_boe/txt.php?id=BOE-A-2026-1',
                ]],
            ], 200),
            '*' => Http::response('<html><body><a href="https://x/oposicions-secundaria-2026.pdf">Oposicions secundària 2026</a></body></html>', 200),
        ]);

        $this->artisan('convocatorias:monitor')->assertSuccessful();

        $this->assertGreaterThanOrEqual(1, Convocatoria::count());
        $conv = Convocatoria::where('comunidad_autonoma', 'nacional')->first();
        $this->assertNotNull($conv);
        $this->assertTrue($conv->pendiente_revision);
        $this->assertSame('anunciada', $conv->estado);

        Notification::assertSentTo($admin, ConvocatoriasDetectadas::class);
    }

    public function test_monitored_convocatorias_are_hidden_from_users_until_reviewed(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Convocatoria::create(['titulo' => 'Pendiente', 'comunidad_autonoma' => 'valenciana', 'estado' => 'anunciada', 'pendiente_revision' => true]);
        Convocatoria::create(['titulo' => 'Publicada', 'comunidad_autonoma' => 'valenciana', 'estado' => 'anunciada', 'pendiente_revision' => false]);

        $this->getJson('/api/v1/convocatorias')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.titulo', 'Publicada');
    }

    public function test_user_can_toggle_convocatoria_alert(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $conv = Convocatoria::create(['titulo' => 'X', 'comunidad_autonoma' => 'valenciana', 'estado' => 'convocada']);

        $this->postJson("/api/v1/convocatorias/{$conv->id}/alert/toggle")
            ->assertOk()->assertJsonPath('alert_active', true);
        $this->assertDatabaseHas('convocatoria_alerts', ['user_id' => $user->id, 'convocatoria_id' => $conv->id]);

        $this->postJson("/api/v1/convocatorias/{$conv->id}/alert/toggle")
            ->assertOk()->assertJsonPath('alert_active', false);
        $this->assertDatabaseCount('convocatoria_alerts', 0);

        // The flag is reflected in the listing.
        $this->postJson("/api/v1/convocatorias/{$conv->id}/alert/toggle");
        $this->getJson('/api/v1/convocatorias')->assertOk()->assertJsonPath('data.0.alert_active', true);
    }

    public function test_estado_change_notifies_alerted_users(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'superadmin']);
        $follower = User::factory()->create();
        $other = User::factory()->create();

        $conv = Convocatoria::create(['titulo' => 'Maestros 2026', 'comunidad_autonoma' => 'valenciana', 'estado' => 'anunciada']);
        ConvocatoriaAlert::create(['user_id' => $follower->id, 'convocatoria_id' => $conv->id]);

        Sanctum::actingAs($admin);
        $this->patchJson("/api/v1/superadmin/convocatorias/{$conv->id}", ['estado' => 'convocada', 'fecha_oficial' => '2026-06-01'])
            ->assertOk()->assertJsonPath('estado', 'convocada');

        Notification::assertSentTo($follower, ConvocatoriaStatusChanged::class);
        Notification::assertNotSentTo($other, ConvocatoriaStatusChanged::class);
    }

    public function test_update_clears_pending_review_flag(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin']);
        Sanctum::actingAs($admin);
        $conv = Convocatoria::create(['titulo' => 'Auto', 'comunidad_autonoma' => 'valenciana', 'estado' => 'anunciada', 'pendiente_revision' => true]);

        $this->patchJson("/api/v1/superadmin/convocatorias/{$conv->id}", ['url_oficial' => 'https://example.com/x'])
            ->assertOk()->assertJsonPath('pendiente_revision', false);
    }

    public function test_normativa_exposes_fuente_and_filters_by_idioma(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        NormativaDocumento::create(['titulo' => 'Cast', 'categoria' => 'decreto', 'comunidad_autonoma' => 'valenciana', 'fuente' => 'dogv', 'idioma' => 'castellano']);
        NormativaDocumento::create(['titulo' => 'Val', 'categoria' => 'decreto', 'comunidad_autonoma' => 'valenciana', 'fuente' => 'dogv', 'idioma' => 'valenciano']);

        $this->getJson('/api/v1/normativa')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.fuente', 'dogv');

        $this->getJson('/api/v1/normativa?idioma=valenciano')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.idioma', 'valenciano');
    }

    public function test_superadmin_sync_endpoints_run(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin']);
        Sanctum::actingAs($admin);

        Http::fake(['*' => Http::response(['results' => []], 200)]);

        $this->postJson('/api/v1/superadmin/normativa/sync-boe')->assertOk()->assertJsonPath('ran', true);
        $this->getJson('/api/v1/superadmin/normativa/sync-state')->assertOk()
            ->assertJsonPath('boe.resumen.found', 0);

        $this->postJson('/api/v1/superadmin/convocatorias/monitor')->assertOk()->assertJsonPath('ran', true);
    }
}
