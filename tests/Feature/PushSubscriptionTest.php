<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_vapid_key_endpoint_reports_availability(): void
    {
        config(['webpush.vapid.public_key' => null]);
        $this->getJson('/api/v1/push/vapid-key')
            ->assertOk()
            ->assertJsonPath('enabled', false);

        config(['webpush.vapid.public_key' => 'BPublicKeyExample']);
        $this->getJson('/api/v1/push/vapid-key')
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('public_key', 'BPublicKeyExample');
    }

    public function test_subscribe_requires_auth(): void
    {
        $this->postJson('/api/v1/push/subscribe', [])->assertUnauthorized();
    }

    public function test_subscribe_stores_and_is_idempotent(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'endpoint' => 'https://push.example.com/abc',
            'keys' => ['p256dh' => 'pkey', 'auth' => 'akey'],
            'contentEncoding' => 'aes128gcm',
        ];

        $this->postJson('/api/v1/push/subscribe', $payload)->assertOk()->assertJsonPath('subscribed', true);
        $this->postJson('/api/v1/push/subscribe', $payload)->assertOk();

        $this->assertSame(1, $user->pushSubscriptions()->count());
        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => 'https://push.example.com/abc',
            'public_key' => 'pkey',
            'content_encoding' => 'aes128gcm',
        ]);
    }

    public function test_web_push_channel_activates_when_configured_and_subscribed(): void
    {
        config(['webpush.vapid.public_key' => 'BPublicKeyExample']);

        $cv = \App\Models\Ccaa::create(['code' => 'CV', 'name' => 'CV', 'is_active' => true]);
        $col = \App\Models\Colectivo::create(['ccaa_id' => $cv->id, 'code' => 'INTERINO', 'name' => 'Interins', 'body' => 'SECUNDARIA']);
        $proceso = \App\Models\Proceso::create([
            'ccaa_id' => $cv->id, 'colectivo_id' => $col->id, 'anyo' => 2026, 'curso' => '2026-2027',
            'nombre' => 'Interins 2026-2027', 'estado' => 'publicado',
        ]);

        $user = User::factory()->create();
        $note = new \App\Notifications\ListadoActualizado($proceso, 'vacantes', ['nuevas' => 1]);

        // No subscription yet → web push channel not used.
        $this->assertNotContains(\App\Notifications\Channels\WebPushChannel::class, $note->via($user));

        $user->pushSubscriptions()->create([
            'endpoint' => 'https://push.example.com/abc', 'public_key' => 'p', 'auth_token' => 'a', 'content_encoding' => 'aesgcm',
        ]);

        $this->assertContains(\App\Notifications\Channels\WebPushChannel::class, $note->via($user->fresh()));
    }

    public function test_unsubscribe_removes_subscription(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $user->pushSubscriptions()->create([
            'endpoint' => 'https://push.example.com/xyz',
            'public_key' => 'pkey',
            'auth_token' => 'akey',
            'content_encoding' => 'aesgcm',
        ]);

        $this->postJson('/api/v1/push/unsubscribe', ['endpoint' => 'https://push.example.com/xyz'])
            ->assertOk()
            ->assertJsonPath('subscribed', false);

        $this->assertSame(0, $user->pushSubscriptions()->count());
    }
}
