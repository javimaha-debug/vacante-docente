<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromoteAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_promotes_existing_user_to_superadmin(): void
    {
        $user = User::factory()->create(['email' => 'javi@example.com']);
        $this->assertSame('user', $user->fresh()->role);

        $this->artisan('admin:promote', ['email' => 'javi@example.com'])
            ->expectsOutputToContain('ahora es super-admin')
            ->assertSuccessful();

        $this->assertSame('superadmin', $user->fresh()->role);
        $this->assertTrue($user->fresh()->isSuperAdmin());
    }

    public function test_fails_when_user_not_found(): void
    {
        $this->artisan('admin:promote', ['email' => 'nope@example.com'])
            ->expectsOutputToContain('No existe ningún usuario')
            ->assertFailed();
    }

    public function test_is_idempotent(): void
    {
        $user = User::factory()->create(['email' => 'twice@example.com']);
        $user->forceFill(['role' => 'superadmin'])->save();

        $this->artisan('admin:promote', ['email' => 'twice@example.com'])
            ->expectsOutputToContain('ya es super-admin')
            ->assertSuccessful();

        $this->assertSame('superadmin', $user->fresh()->role);
    }
}
