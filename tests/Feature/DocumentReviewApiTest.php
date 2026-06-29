<?php

namespace Tests\Feature;

use App\Models\AcademicCalendarEvent;
use App\Models\DetectedDocument;
use App\Models\MonitoredSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentReviewApiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => 'superadmin'])->save();

        return $u->fresh();
    }

    private function doc(array $attrs = []): DetectedDocument
    {
        $source = MonitoredSource::create(['name' => 'S', 'url' => 'https://s.test', 'type' => 'gva']);

        return DetectedDocument::create(array_merge([
            'source_id' => $source->id, 'title' => 'Llistat provisional', 'detected_at' => now(),
            'document_type' => 'listado_provisional', 'status' => 'pending',
        ], $attrs));
    }

    public function test_endpoints_require_superadmin(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/superadmin/documents')->assertForbidden();
        $this->getJson('/api/v1/superadmin/calendar')->assertForbidden();
    }

    public function test_validate_then_publish_flow_confirms_event(): void
    {
        $doc = $this->doc();
        $event = AcademicCalendarEvent::create([
            'title' => 'Sugerido', 'event_type' => 'listado_provisional', 'event_date' => '2026-07-22',
            'source_document_id' => $doc->id, 'is_confirmed' => false, 'is_estimated' => true, 'visibility' => 'superadmin_only',
        ]);

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/superadmin/documents/{$doc->id}/validate", ['notes' => 'Revisado'])
            ->assertOk()->assertJsonPath('status', 'validated');

        $this->postJson("/api/v1/superadmin/documents/{$doc->id}/publish", ['confirm_event' => true])
            ->assertOk()->assertJsonPath('status', 'published');

        $this->assertDatabaseHas('detected_documents', ['id' => $doc->id, 'status' => 'published']);
        $this->assertDatabaseHas('academic_calendar_events', [
            'id' => $event->id, 'is_confirmed' => true, 'is_estimated' => false, 'visibility' => 'public',
        ]);
    }

    public function test_reject_sets_status(): void
    {
        $doc = $this->doc();
        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/superadmin/documents/{$doc->id}/reject", ['notes' => 'No relevante'])
            ->assertOk()->assertJsonPath('status', 'rejected');
    }

    public function test_index_filters_by_status(): void
    {
        $this->doc(['status' => 'pending', 'title' => 'P']);
        $this->doc(['status' => 'published', 'title' => 'Q', 'published_at' => now()]);
        Sanctum::actingAs($this->admin());

        $res = $this->getJson('/api/v1/superadmin/documents?status=published')->assertOk()->json();
        $this->assertCount(1, $res['data']);
        $this->assertSame('Q', $res['data'][0]['title']);
    }

    public function test_manual_upload_creates_validated_document(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/superadmin/documents/upload', [
            'document_type' => 'listado_definitivo',
            'title' => 'Subida manual',
            'pdf' => UploadedFile::fake()->create('lista.pdf', 40, 'application/pdf'),
            'publish_now' => true,
        ])->assertCreated()->assertJsonPath('status', 'published');

        $this->assertDatabaseHas('detected_documents', ['title' => 'Subida manual', 'status' => 'published']);
    }

    public function test_stats_returns_pending_count(): void
    {
        $this->doc(['status' => 'pending']);
        $this->doc(['status' => 'pending', 'title' => 'P2']);
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/v1/superadmin/documents/stats')
            ->assertOk()->assertJsonPath('pendientes', 2);
    }
}
