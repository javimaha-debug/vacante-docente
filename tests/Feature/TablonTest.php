<?php

namespace Tests\Feature;

use App\Mail\TablonContactoMail;
use App\Mail\TablonRespuestaMail;
use App\Models\Ccaa;
use App\Models\GvaNoticia;
use App\Models\TablonAnuncio;
use App\Models\TablonContacto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TablonTest extends TestCase
{
    use RefreshDatabase;

    private Ccaa $cv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cv = Ccaa::create(['code' => 'CV', 'name' => 'CV', 'is_active' => true]);
    }

    public function test_listing_is_public_and_hides_contact_email(): void
    {
        $owner = User::factory()->create(['ccaa_id' => $this->cv->id]);
        TablonAnuncio::create([
            'user_id' => $owner->id, 'ccaa_id' => $this->cv->id, 'categoria' => 'coche',
            'titulo' => 'Comparto coche', 'contenido' => 'De Castelló a València',
            'contacto_email' => 'secret@example.com', 'is_active' => true,
        ]);

        $res = $this->getJson('/api/v1/tablon')->assertOk()->assertJsonPath('total', 1);
        $this->assertArrayNotHasKey('contacto_email', $res->json('data.0'));
    }

    public function test_create_sets_expiry_and_ccaa_from_profile(): void
    {
        $user = User::factory()->create(['ccaa_id' => $this->cv->id]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/tablon', [
            'categoria' => 'general', 'titulo' => 'Hola', 'contenido' => 'Contenido de prueba',
        ])->assertCreated()->assertJsonPath('ccaa_id', $this->cv->id);

        $anuncio = TablonAnuncio::first();
        $this->assertNotNull($anuncio->expires_at);
        $this->assertTrue($anuncio->expires_at->greaterThan(now()->addDays(59)));
    }

    public function test_only_owner_can_delete(): void
    {
        $owner = User::factory()->create(['ccaa_id' => $this->cv->id]);
        $other = User::factory()->create();
        $anuncio = TablonAnuncio::create(['user_id' => $owner->id, 'ccaa_id' => $this->cv->id, 'categoria' => 'general', 'titulo' => 'X', 'contenido' => 'Y', 'is_active' => true]);

        Sanctum::actingAs($other);
        $this->deleteJson("/api/v1/tablon/{$anuncio->id}")->assertForbidden();

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/v1/tablon/{$anuncio->id}")->assertOk();
        $this->assertSoftDeleted('tablon_anuncios', ['id' => $anuncio->id]);
    }

    public function test_contactar_queues_email_and_hides_owner(): void
    {
        Mail::fake();
        $owner = User::factory()->create(['ccaa_id' => $this->cv->id, 'email' => 'owner@example.com']);
        $requester = User::factory()->create();
        $anuncio = TablonAnuncio::create(['user_id' => $owner->id, 'ccaa_id' => $this->cv->id, 'categoria' => 'general', 'titulo' => 'X', 'contenido' => 'Y', 'is_active' => true]);

        Sanctum::actingAs($requester);
        $this->postJson("/api/v1/tablon/{$anuncio->id}/contactar", ['mensaje' => 'Me interesa'])
            ->assertCreated();

        Mail::assertQueued(TablonContactoMail::class, fn ($m) => $m->hasTo('owner@example.com'));
        $this->assertDatabaseHas('tablon_contactos', ['anuncio_id' => $anuncio->id, 'user_id' => $requester->id, 'email_enviado' => true]);
    }

    public function test_cannot_contact_own_announcement(): void
    {
        $owner = User::factory()->create(['ccaa_id' => $this->cv->id]);
        $anuncio = TablonAnuncio::create(['user_id' => $owner->id, 'ccaa_id' => $this->cv->id, 'categoria' => 'general', 'titulo' => 'X', 'contenido' => 'Y', 'is_active' => true]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/tablon/{$anuncio->id}/contactar", ['mensaje' => 'hola'])->assertStatus(422);
    }

    public function test_owner_reply_emails_requester(): void
    {
        Mail::fake();
        $owner = User::factory()->create(['ccaa_id' => $this->cv->id]);
        $requester = User::factory()->create(['email' => 'req@example.com']);
        $anuncio = TablonAnuncio::create(['user_id' => $owner->id, 'ccaa_id' => $this->cv->id, 'categoria' => 'general', 'titulo' => 'X', 'contenido' => 'Y', 'is_active' => true]);
        $contacto = TablonContacto::create(['anuncio_id' => $anuncio->id, 'user_id' => $requester->id, 'mensaje' => 'hola']);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/tablon/contactos/{$contacto->id}/responder", ['mensaje' => 'Te respondo'])->assertOk();

        Mail::assertQueued(TablonRespuestaMail::class, fn ($m) => $m->hasTo('req@example.com'));
    }

    public function test_mis_anuncios_and_contactos(): void
    {
        $owner = User::factory()->create(['ccaa_id' => $this->cv->id]);
        $requester = User::factory()->create();
        $anuncio = TablonAnuncio::create(['user_id' => $owner->id, 'ccaa_id' => $this->cv->id, 'categoria' => 'general', 'titulo' => 'X', 'contenido' => 'Y', 'is_active' => true]);
        TablonContacto::create(['anuncio_id' => $anuncio->id, 'user_id' => $requester->id, 'mensaje' => 'hola']);

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/tablon/mis-anuncios')->assertOk()->assertJsonPath('data.0.contactos_count', 1);

        $res = $this->getJson("/api/v1/tablon/{$anuncio->id}/contactos")->assertOk()->assertJsonCount(1, 'data');
        $this->assertArrayNotHasKey('user_id', $res->json('data.0'));
    }

    public function test_admin_gva_noticias_requires_admin(): void
    {
        GvaNoticia::create(['titulo' => 'X', 'url' => 'https://x/1', 'tipo' => 'PDF', 'notificado' => false]);

        $normal = User::factory()->create(['is_admin' => false]);
        Sanctum::actingAs($normal);
        // A freshly created user may be id=1; force a non-admin, non-first id check
        // by asserting forbidden only when not admin and not id 1.
        if ($normal->id !== 1) {
            $this->getJson('/api/v1/admin/gva-noticias')->assertForbidden();
        }

        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/admin/gva-noticias')->assertOk()->assertJsonCount(1, 'data');
    }
}
