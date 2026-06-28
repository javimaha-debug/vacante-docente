<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmailPasswordAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_and_returns_token(): void
    {
        $res = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ana Pérez',
            'email' => 'ana@example.com',
            'password' => 'secretpass1',
            'password_confirmation' => 'secretpass1',
        ])->assertCreated()->assertJsonStructure(['token', 'user' => ['id', 'email']]);

        $this->assertDatabaseHas('users', ['email' => 'ana@example.com', 'name' => 'Ana Pérez']);

        // The returned token authenticates against the API.
        $token = $res->json('token');
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/user/me')->assertOk()->assertJsonPath('email', 'ana@example.com');
    }

    public function test_register_validates_unique_email_and_password_confirmation(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'X', 'email' => 'taken@example.com', 'password' => 'secretpass1', 'password_confirmation' => 'secretpass1',
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        $this->postJson('/api/v1/auth/register', [
            'name' => 'X', 'email' => 'new@example.com', 'password' => 'secretpass1', 'password_confirmation' => 'nope',
        ])->assertStatus(422)->assertJsonValidationErrors('password');

        $this->postJson('/api/v1/auth/register', [
            'name' => 'X', 'email' => 'short@example.com', 'password' => 'short', 'password_confirmation' => 'short',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_login_with_correct_and_incorrect_password(): void
    {
        User::factory()->create(['email' => 'bob@example.com', 'password' => Hash::make('rightpass1')]);

        $this->postJson('/api/v1/auth/login', ['email' => 'bob@example.com', 'password' => 'rightpass1'])
            ->assertOk()->assertJsonStructure(['token', 'user']);

        $this->postJson('/api/v1/auth/login', ['email' => 'bob@example.com', 'password' => 'wrong'])
            ->assertStatus(422)->assertJsonValidationErrors('email');

        $this->postJson('/api/v1/auth/login', ['email' => 'ghost@example.com', 'password' => 'whatever'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_providers_endpoint_lists_configured_social_logins(): void
    {
        config(['services.google.client_id' => 'abc', 'services.microsoft.client_id' => null]);

        $this->getJson('/api/v1/auth/providers')
            ->assertOk()
            ->assertJsonPath('password', true)
            ->assertJsonPath('providers', ['google']);

        config(['services.microsoft.client_id' => 'mmm']);
        $this->getJson('/api/v1/auth/providers')
            ->assertOk()
            ->assertJsonFragment(['providers' => ['google', 'microsoft']]);
    }

    public function test_oauth_redirect_is_disabled_when_provider_not_configured(): void
    {
        config(['services.microsoft.client_id' => null]);

        $this->get('/auth/microsoft')->assertRedirect('/?error=oauth_provider');
    }
}
