<?php

namespace Tests\Feature;

use App\Jobs\ProcessDocumentJob;
use App\Models\User;
use App\Models\UserDocument;
use App\Models\UserFolder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentAreaTest extends TestCase
{
    use RefreshDatabase;

    private function disk(): string
    {
        return config('documents.disk');
    }

    public function test_endpoints_require_auth(): void
    {
        $this->getJson('/api/v1/documents')->assertUnauthorized();
        $this->getJson('/api/v1/folders')->assertUnauthorized();
    }

    public function test_upload_stores_file_increments_storage_and_queues_processing(): void
    {
        Storage::fake($this->disk());
        Queue::fake();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $res = $this->postJson('/api/v1/documents/upload', [
            'files' => [UploadedFile::fake()->create('apuntes.pdf', 200, 'application/pdf')],
        ])->assertCreated()->json();

        $this->assertCount(1, $res['data']);
        $doc = UserDocument::first();
        $this->assertSame('apuntes.pdf', $doc->name);
        $this->assertSame('pdf', $doc->type);
        $this->assertSame('pending', $doc->processing_status);
        Storage::disk($this->disk())->assertExists($doc->disk_path);

        // 200 KB file → storage_used_bytes grew.
        $this->assertGreaterThan(0, $user->fresh()->storage_used_bytes);
        Queue::assertPushed(ProcessDocumentJob::class);
    }

    public function test_upload_blocked_when_over_quota(): void
    {
        Storage::fake($this->disk());
        $user = User::factory()->create();
        $user->forceFill(['storage_limit_bytes' => 1000, 'storage_used_bytes' => 0])->save();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/documents/upload', [
            'files' => [UploadedFile::fake()->create('big.pdf', 50, 'application/pdf')], // 50 KB > 1000 B
        ])->assertStatus(422)->assertJsonStructure(['message', 'storage_used_bytes', 'storage_limit_bytes']);

        $this->assertSame(0, UserDocument::count());
    }

    public function test_disallowed_extension_is_rejected(): void
    {
        Storage::fake($this->disk());
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/documents/upload', [
            'files' => [UploadedFile::fake()->create('virus.exe', 10)],
        ])->assertStatus(422);
    }

    public function test_list_filters_by_folder_and_type(): void
    {
        Storage::fake($this->disk());
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $folder = UserFolder::create(['user_id' => $user->id, 'name' => 'Tema 1']);
        UserDocument::create(['user_id' => $user->id, 'folder_id' => $folder->id, 'name' => 'a.pdf', 'disk_path' => 'p/a', 'type' => 'pdf', 'size_bytes' => 1]);
        UserDocument::create(['user_id' => $user->id, 'name' => 'b.png', 'disk_path' => 'p/b', 'type' => 'image', 'size_bytes' => 1]);

        $this->getJson("/api/v1/documents?folder_id={$folder->id}")->assertOk()->assertJsoncount(1, 'data');
        $this->getJson('/api/v1/documents?type=image')->assertOk()->assertJsonPath('data.0.name', 'b.png');
    }

    public function test_show_returns_signed_view_url_that_streams_the_file(): void
    {
        Storage::fake($this->disk());
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Storage::disk($this->disk())->put('users/x/docs/file.pdf', '%PDF-1.4 data');
        $doc = UserDocument::create([
            'user_id' => $user->id, 'name' => 'f.pdf', 'disk_path' => 'users/x/docs/file.pdf',
            'type' => 'pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 12,
        ]);

        $url = $this->getJson("/api/v1/documents/{$doc->id}")->assertOk()->json('view_url');
        $this->assertNotEmpty($url);

        // The signed URL streams the file; tampering breaks the signature.
        $this->get($url)->assertOk();
        $this->get($url.'&tampered=1')->assertForbidden();
    }

    public function test_cannot_access_another_users_document(): void
    {
        Storage::fake($this->disk());
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $doc = UserDocument::create(['user_id' => $owner->id, 'name' => 'x', 'disk_path' => 'p/x', 'type' => 'pdf', 'size_bytes' => 1]);

        Sanctum::actingAs($other);
        $this->getJson("/api/v1/documents/{$doc->id}")->assertForbidden();
        $this->deleteJson("/api/v1/documents/{$doc->id}")->assertForbidden();
    }

    public function test_move_and_delete_update_storage(): void
    {
        Storage::fake($this->disk());
        $user = User::factory()->create();
        $user->forceFill(['storage_used_bytes' => 500])->save();
        Sanctum::actingAs($user);

        Storage::disk($this->disk())->put('p/x', 'data');
        $folder = UserFolder::create(['user_id' => $user->id, 'name' => 'F']);
        $doc = UserDocument::create(['user_id' => $user->id, 'name' => 'x', 'disk_path' => 'p/x', 'type' => 'pdf', 'size_bytes' => 200]);

        $this->postJson("/api/v1/documents/{$doc->id}/move", ['folder_id' => $folder->id])
            ->assertOk()->assertJsonPath('folder_id', $folder->id);

        $this->deleteJson("/api/v1/documents/{$doc->id}")->assertOk();
        $this->assertSame(0, UserDocument::count());
        Storage::disk($this->disk())->assertMissing('p/x');
        $this->assertSame(300, $user->fresh()->storage_used_bytes); // 500 - 200
    }

    public function test_folder_crud_and_delete_moves_docs_to_root(): void
    {
        Storage::fake($this->disk());
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $id = $this->postJson('/api/v1/folders', ['name' => 'Constitución', 'color' => '#0e6e5e'])->assertCreated()->json('id');
        $doc = UserDocument::create(['user_id' => $user->id, 'folder_id' => $id, 'name' => 'x', 'disk_path' => 'p/x', 'type' => 'pdf', 'size_bytes' => 1]);

        $this->patchJson("/api/v1/folders/{$id}", ['name' => 'Renombrada'])->assertOk()->assertJsonPath('name', 'Renombrada');
        $this->getJson('/api/v1/folders')->assertOk()->assertJsonPath('data.0.name', 'Renombrada');

        $this->deleteJson("/api/v1/folders/{$id}")->assertOk();
        $this->assertNull($doc->fresh()->folder_id);
    }

    public function test_tags_attach_via_patch(): void
    {
        Storage::fake($this->disk());
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $doc = UserDocument::create(['user_id' => $user->id, 'name' => 'x', 'disk_path' => 'p/x', 'type' => 'pdf', 'size_bytes' => 1]);
        $tagId = $this->postJson('/api/v1/document-tags', ['name' => 'Importante'])->assertCreated()->json('id');

        $this->patchJson("/api/v1/documents/{$doc->id}", ['tag_ids' => [$tagId]])->assertOk();
        $this->assertTrue($doc->fresh()->tags()->where('user_document_tags.id', $tagId)->exists());
    }

    public function test_process_job_sets_page_count_and_queues_extraction(): void
    {
        // ProcessDocumentJob now hands off to the RAG chain (extract → chunk →
        // embed), which sets the final 'ready'. Fake the queue so only this job runs.
        \Illuminate\Support\Facades\Queue::fake();
        Storage::fake($this->disk());
        $user = User::factory()->create();
        Storage::disk($this->disk())->put('p/img.png', 'binary');
        $doc = UserDocument::create(['user_id' => $user->id, 'name' => 'i.png', 'disk_path' => 'p/img.png', 'type' => 'image', 'size_bytes' => 6]);

        (new ProcessDocumentJob($doc->id))->handle();

        $this->assertSame(1, $doc->fresh()->page_count);
        $this->assertSame('processing', $doc->fresh()->processing_status);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\ExtractDocumentTextJob::class);
    }

    public function test_integration_connect_requires_configuration(): void
    {
        config(['services.google.client_id' => null]);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/integrations/google-drive/connect')->assertStatus(503);
    }

    public function test_integration_connect_returns_url_when_configured(): void
    {
        config(['services.google.client_id' => 'cid', 'services.google.client_secret' => 'secret']);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/integrations/google-drive/connect')
            ->assertOk()
            ->assertJsonStructure(['url']);
    }
}
