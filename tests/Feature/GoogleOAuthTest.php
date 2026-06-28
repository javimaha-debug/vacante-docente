<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class GoogleOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Social login is gated on configured credentials.
        config(['services.google.client_id' => 'test-client-id', 'services.google.client_secret' => 'test-secret']);
    }

    private function fakeGoogleUser(string $email, string $name, ?string $avatar): void
    {
        $socialiteUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-123');
        $socialiteUser->shouldReceive('getName')->andReturn($name);
        $socialiteUser->shouldReceive('getEmail')->andReturn($email);
        $socialiteUser->shouldReceive('getAvatar')->andReturn($avatar);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_redirect_to_google_hits_the_provider(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get('/auth/google')->assertRedirect('https://accounts.google.com/o/oauth2');
    }

    public function test_callback_creates_user_and_redirects_with_token(): void
    {
        $this->fakeGoogleUser('teacher@example.com', 'Ada Docent', 'https://photo/ada.jpg');

        $response = $this->get('/auth/google/callback');

        // The callback hands over a single-use code, not the token itself.
        $response->assertRedirectContains('/dashboard?code=');
        $this->assertStringNotContainsString('token=', $response->headers->get('Location'));

        $user = User::where('email', 'teacher@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('Ada Docent', $user->name);
        $this->assertSame('https://photo/ada.jpg', $user->avatar_url);
        $this->assertNull($user->nombre_gva);
        $this->assertSame('es', $user->locale);
        $this->assertSame(1, $user->tokens()->count());

        // The code exchanges for a working token exactly once.
        $code = \Illuminate\Support\Str::after($response->headers->get('Location'), 'code=');
        $this->postJson('/api/v1/auth/exchange', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('user.email', 'teacher@example.com')
            ->assertJsonStructure(['token', 'user']);
        // Single-use: a second exchange fails.
        $this->postJson('/api/v1/auth/exchange', ['code' => $code])->assertStatus(422);
    }

    public function test_callback_is_idempotent_for_existing_user_and_preserves_nombre_gva(): void
    {
        $existing = User::factory()->create([
            'email' => 'teacher@example.com',
            'nombre_gva' => 'PEREZ GOMEZ, ANA',
            'locale' => 'ca',
        ]);

        $this->fakeGoogleUser('teacher@example.com', 'Ana Updated', 'https://photo/new.jpg');

        $this->get('/auth/google/callback')->assertRedirectContains('/dashboard?code=');

        $existing->refresh();
        $this->assertSame(1, User::where('email', 'teacher@example.com')->count());
        // Existing profile data must survive a re-login.
        $this->assertSame('PEREZ GOMEZ, ANA', $existing->nombre_gva);
        $this->assertSame('ca', $existing->locale);
        $this->assertSame('https://photo/new.jpg', $existing->avatar_url);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('google-spa')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/auth/logout')
            ->assertOk();

        $this->assertSame(0, $user->fresh()->tokens()->count());
    }
}
