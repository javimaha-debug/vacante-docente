<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\Integrations\GoogleDriveController;
use App\Models\AiConversation;
use App\Models\Specialty;
use App\Models\User;
use App\Models\UserDocument;
use App\Models\UserList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SecuritySprintTest extends TestCase
{
    use RefreshDatabase;

    /** SEC-H2 */
    public function test_user_cannot_access_another_users_documents(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $doc = UserDocument::create([
            'user_id' => $owner->id, 'name' => 'a.pdf', 'disk_path' => 'x/a.pdf',
            'type' => 'pdf', 'source' => 'upload', 'processing_status' => 'done',
        ]);

        // Authenticated detail endpoint is owner-scoped.
        Sanctum::actingAs($other);
        $this->getJson("/api/v1/documents/{$doc->id}")->assertForbidden();

        // The signed stream is bound to the owner's uid; a tampered uid 403s.
        $tampered = URL::temporarySignedRoute('documents.view', now()->addMinutes(10), [
            'document' => $doc->id, 'uid' => $other->id,
        ]);
        $this->get($tampered)->assertForbidden();
    }

    /** SEC-C1 (regression in this suite too) */
    public function test_user_cannot_access_another_users_list(): void
    {
        $sp = Specialty::create(['code' => '121', 'name' => 'P', 'body' => 'Maestros', 'education_level' => 'maestros', 'is_active' => true]);
        $list = UserList::create(['session_token' => 'owner-token-aaaaaaaa', 'specialty_id' => $sp->id]);

        $this->withHeader('X-Session-Token', 'attacker-token-bbbbbbbb')
            ->getJson("/api/v1/user-lists/{$list->id}/preferences")
            ->assertForbidden();
    }

    /** SEC-H1 */
    public function test_regular_user_cannot_access_superadmin_routes(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));

        // Role-based admin routes (GVA) and the superadmin panel are blocked.
        $this->getJson('/api/v1/admin/gva-noticias')->assertForbidden();
        $this->getJson('/api/v1/superadmin/temarios')->assertForbidden();
    }

    /** SEC-H1 — SSRF allow-list on manual import */
    public function test_manual_import_rejects_disallowed_domain(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'superadmin']));

        $this->postJson('/api/v1/admin/importaciones/manual', [
            'url' => 'http://169.254.169.254/latest/meta-data/', 'tipo' => 'continua',
        ])->assertStatus(422);
    }

    /** SEC-H4 */
    public function test_ai_endpoint_rate_limited_per_minute(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $conv = AiConversation::create([
            'user_id' => $user->id, 'mode' => 'chat', 'context_type' => 'free',
        ]);

        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'ok']],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ], 200)]);

        $status = 200;
        for ($i = 0; $i < 12; $i++) {
            $status = $this->postJson("/api/v1/ai/conversations/{$conv->id}/message", ['content' => 'hola'])->status();
            if ($status === 429) {
                break;
            }
        }

        $this->assertSame(429, $status, 'AI message endpoint should rate-limit per minute.');
    }

    /** SEC-H3 (direct upload path) */
    public function test_file_upload_rejects_php_files(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $php = UploadedFile::fake()->createWithContent('shell.php', "<?php echo 'x';");
        $this->postJson('/api/v1/documents/upload', ['files' => [$php]])
            ->assertStatus(422)->assertJsonValidationErrors('files.0');
    }

    /** SEC-H3 (size cap) */
    public function test_file_upload_rejects_oversized_files(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $maxKb = (int) config('documents.max_kb');
        $big = UploadedFile::fake()->create('big.pdf', $maxKb + 1024, 'application/pdf');
        $this->postJson('/api/v1/documents/upload', ['files' => [$big]])
            ->assertStatus(422)->assertJsonValidationErrors('files.0');
    }

    /** SEC-H3 (cloud import real-MIME check) */
    public function test_cloud_import_validates_mime_type(): void
    {
        $controller = new GoogleDriveController;
        $method = new \ReflectionMethod($controller, 'assertContentMatchesExtension');
        $method->setAccessible(true);

        // A PHP payload claiming to be a PDF must be rejected (422).
        try {
            $method->invoke($controller, "<?php system(\$_GET['c']); ?>", 'pdf');
            $this->fail('Expected a 422 for mismatched content.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        // A real PDF payload passes.
        $method->invoke($controller, "%PDF-1.4\n%âãÏÓ\n", 'pdf');
        $this->assertTrue(true);
    }

    /** SEC-M1 */
    public function test_oauth_state_cannot_be_reused(): void
    {
        $controller = new GoogleDriveController;
        $method = new \ReflectionMethod($controller, 'userFromState');
        $method->setAccessible(true);

        $nonce = 'nonce-single-use-test';
        Cache::put('cloud_oauth_nonce:'.$nonce, 7, now()->addMinutes(10));
        $state = Crypt::encryptString(json_encode([
            'uid' => 7, 'nonce' => $nonce, 'exp' => now()->addMinutes(10)->timestamp,
        ]));

        // First use resolves the user; the second is rejected (nonce consumed).
        $this->assertSame(7, $method->invoke($controller, $state));
        $this->assertNull($method->invoke($controller, $state));
    }
}
