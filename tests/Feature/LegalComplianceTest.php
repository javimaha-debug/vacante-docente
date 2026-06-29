<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LegalComplianceTest extends TestCase
{
    use RefreshDatabase;

    /** Registration must require accepting the Terms + Privacy Policy. */
    public function test_registration_requires_consent(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            // no acepto_condiciones
        ])->assertStatus(422)->assertJsonValidationErrors('acepto_condiciones');

        $this->assertDatabaseMissing('users', ['email' => 'ana@example.com']);
    }

    /** With consent, registration succeeds and records the moment. */
    public function test_registration_records_consent_timestamp(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'acepto_condiciones' => true,
        ])->assertCreated();

        $user = User::where('email', 'ana@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->terms_accepted_at);
    }

    /** The export endpoint returns the caller's own data only (RGPD art. 20). */
    public function test_user_can_export_their_data(): void
    {
        $user = User::factory()->create(['name' => 'Bea', 'nombre_gva' => 'GARCIA, BEA']);
        AiConversation::create(['user_id' => $user->id, 'mode' => 'chat', 'context_type' => 'free', 'title' => 'Mi duda']);
        Sanctum::actingAs($user);

        $res = $this->getJson('/api/v1/user/export')->assertOk();
        $res->assertJsonPath('cuenta.email', $user->email);
        $res->assertJsonPath('cuenta.nombre_gva', 'GARCIA, BEA');
        $this->assertNotEmpty($res->json('conversaciones_ia'));
    }

    /** Deletion needs the typed confirmation word (RGPD art. 17). */
    public function test_account_deletion_requires_confirmation(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson('/api/v1/user/account', ['confirmacion' => 'nope'])
            ->assertStatus(422)->assertJsonValidationErrors('confirmacion');
    }

    /** A confirmed deletion wipes the account and its related rows. */
    public function test_user_can_delete_their_account(): void
    {
        Storage::fake(config('documents.disk'));
        $user = User::factory()->create();
        UserDocument::create([
            'user_id' => $user->id, 'name' => 'a.pdf', 'disk_path' => 'x/a.pdf',
            'type' => 'pdf', 'source' => 'upload', 'processing_status' => 'done',
        ]);
        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/user/account', ['confirmacion' => 'ELIMINAR'])
            ->assertOk()->assertJson(['deleted' => true]);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('user_documents', ['user_id' => $user->id]);
    }
}
