<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HeroGreetingTest extends TestCase
{
    use RefreshDatabase;

    public function test_hero_uses_account_name_capitalised_not_nombre_gva(): void
    {
        $user = User::factory()->create(['name' => 'javier valcárcel']);
        $user->forceFill(['nombre_gva' => 'VALCARCEL GARCIA, JAVIER'])->save();

        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/v1/user/hero')
            ->assertOk()
            // First name from `name`, capitalised — not the nombre_gva surname.
            ->assertJsonPath('nombre', 'Javier');
    }

    public function test_hero_capitalises_uppercase_name(): void
    {
        Sanctum::actingAs(User::factory()->create(['name' => 'ANA PÉREZ']));

        $this->getJson('/api/v1/user/hero')
            ->assertOk()
            ->assertJsonPath('nombre', 'Ana');
    }
}
