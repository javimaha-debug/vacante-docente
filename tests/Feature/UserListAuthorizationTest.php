<?php

namespace Tests\Feature;

use App\Models\Specialty;
use App\Models\User;
use App\Models\UserList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for SEC-C1: the user-lists/* routes must not let one
 * session read or modify another session's list (IDOR via id enumeration).
 */
class UserListAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Specialty $specialty;

    protected function setUp(): void
    {
        parent::setUp();
        $this->specialty = Specialty::create([
            'code' => '121', 'name' => 'Educación Primaria', 'body' => 'Maestros',
            'education_level' => 'maestros', 'is_active' => true,
        ]);
    }

    private function makeList(string $token = 'owner-secret-token-aaaa', ?int $userId = null): UserList
    {
        return UserList::create([
            'session_token' => $token,
            'specialty_id' => $this->specialty->id,
            'user_id' => $userId,
            'home_address' => 'Calle Secreta 1',
            'home_lat' => 39.47, 'home_lng' => -0.37,
        ]);
    }

    public function test_owner_with_session_token_can_read_preferences(): void
    {
        $list = $this->makeList();

        $this->withHeader('X-Session-Token', 'owner-secret-token-aaaa')
            ->getJson("/api/v1/user-lists/{$list->id}/preferences")
            ->assertOk()->assertJsonStructure(['data']);
    }

    public function test_attacker_cannot_read_another_lists_preferences(): void
    {
        $list = $this->makeList();

        // Wrong token.
        $this->withHeader('X-Session-Token', 'attacker-other-token-bbbb')
            ->getJson("/api/v1/user-lists/{$list->id}/preferences")
            ->assertForbidden();

        // No token at all.
        $this->getJson("/api/v1/user-lists/{$list->id}/preferences")
            ->assertForbidden();
    }

    public function test_attacker_cannot_update_home_address(): void
    {
        $list = $this->makeList();

        $this->withHeader('X-Session-Token', 'attacker-other-token-bbbb')
            ->patchJson("/api/v1/user-lists/{$list->id}", ['home_address' => 'Hackeado'])
            ->assertForbidden();

        $this->assertSame('Calle Secreta 1', $list->fresh()->home_address);
    }

    public function test_owner_can_update_home_address(): void
    {
        $list = $this->makeList();

        $this->withHeader('X-Session-Token', 'owner-secret-token-aaaa')
            ->patchJson("/api/v1/user-lists/{$list->id}", ['home_address' => 'Calle Nueva 2'])
            ->assertOk();

        $this->assertSame('Calle Nueva 2', $list->fresh()->home_address);
    }

    public function test_attacker_cannot_bulk_write_preferences(): void
    {
        $list = $this->makeList();

        $this->withHeader('X-Session-Token', 'attacker-other-token-bbbb')
            ->putJson("/api/v1/user-lists/{$list->id}/preferences/bulk", ['preferences' => []])
            ->assertForbidden();
    }

    public function test_owner_can_bulk_write_preferences(): void
    {
        $list = $this->makeList();

        $this->withHeader('X-Session-Token', 'owner-secret-token-aaaa')
            ->putJson("/api/v1/user-lists/{$list->id}/preferences/bulk", ['preferences' => []])
            ->assertOk();
    }

    public function test_attacker_cannot_geocode_another_list(): void
    {
        $list = $this->makeList();

        // Authorization runs before the maps-config check, so this is a 403.
        $this->withHeader('X-Session-Token', 'attacker-other-token-bbbb')
            ->postJson("/api/v1/user-lists/{$list->id}/geocode", ['address' => 'Madrid'])
            ->assertForbidden();
    }

    public function test_authenticated_owner_can_access_via_user_id(): void
    {
        $user = User::factory()->create();
        // List owned by the account, with a session token the caller does NOT send.
        $list = $this->makeList('some-unknown-token-cccc', $user->id);

        // Real bearer token — these routes have no auth:sanctum middleware, so
        // ownership is resolved from the token by the sanctum guard directly.
        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/user-lists/{$list->id}/preferences")
            ->assertOk();
    }

    public function test_authenticated_non_owner_is_forbidden(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $list = $this->makeList('owner-secret-token-aaaa', $owner->id);

        $token = $other->createToken('test')->plainTextToken;
        // Different account, and no matching session token → forbidden.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/user-lists/{$list->id}/preferences")
            ->assertForbidden();
    }
}
