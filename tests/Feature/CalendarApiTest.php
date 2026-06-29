<?php

namespace Tests\Feature;

use App\Models\AcademicCalendarEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CalendarApiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => 'superadmin'])->save();

        return $u->fresh();
    }

    private function event(array $attrs = []): AcademicCalendarEvent
    {
        return AcademicCalendarEvent::create(array_merge([
            'title' => 'Evento', 'event_type' => 'listado_provisional', 'event_date' => '2026-07-22',
            'is_confirmed' => false, 'is_estimated' => true, 'affects' => 'interinos', 'visibility' => 'superadmin_only',
        ], $attrs));
    }

    public function test_superadmin_can_create_and_confirm_event(): void
    {
        Sanctum::actingAs($this->admin());

        $id = $this->postJson('/api/v1/superadmin/calendar', [
            'title' => 'Listado provisional', 'event_type' => 'listado_provisional',
            'event_date' => '2026-07-22', 'affects' => 'interinos',
        ])->assertCreated()->json('id');

        $this->postJson("/api/v1/superadmin/calendar/{$id}/confirm")
            ->assertOk()->assertJsonPath('is_confirmed', true)->assertJsonPath('is_estimated', false);
    }

    public function test_superadmin_can_edit_and_delete(): void
    {
        $event = $this->event();
        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/v1/superadmin/calendar/{$event->id}", ['title' => 'Cambiado', 'event_date' => '2026-08-01'])
            ->assertOk()->assertJsonPath('title', 'Cambiado');

        $this->deleteJson("/api/v1/superadmin/calendar/{$event->id}")->assertOk();
        $this->assertDatabaseMissing('academic_calendar_events', ['id' => $event->id]);
    }

    public function test_suggested_filter_returns_only_unconfirmed_with_source(): void
    {
        $this->event(['source_document_id' => null]); // not suggested
        // Suggested needs a source document; create one via factory-less insert.
        $source = \App\Models\MonitoredSource::create(['name' => 'S', 'url' => 'https://s.test', 'type' => 'gva']);
        $doc = \App\Models\DetectedDocument::create([
            'source_id' => $source->id, 'title' => 'D', 'document_type' => 'listado_provisional', 'status' => 'pending',
        ]);
        $this->event(['source_document_id' => $doc->id, 'title' => 'Sugerido']);

        Sanctum::actingAs($this->admin());

        $res = $this->getJson('/api/v1/superadmin/calendar?suggested=1')->assertOk()->json();
        $this->assertCount(1, $res['data']);
        $this->assertSame('Sugerido', $res['data'][0]['title']);
    }

    public function test_public_calendar_only_returns_confirmed_visible_events(): void
    {
        $this->event(['title' => 'Oculto', 'visibility' => 'superadmin_only', 'is_confirmed' => false]);
        $this->event(['title' => 'Visible', 'visibility' => 'public', 'is_confirmed' => true, 'is_estimated' => false, 'event_date' => '2030-01-01']);
        $this->event(['title' => 'Solo usuarios', 'visibility' => 'users_only', 'is_confirmed' => true, 'event_date' => '2030-02-01']);

        Sanctum::actingAs(User::factory()->create());

        $res = $this->getJson('/api/v1/calendar')->assertOk()->json();
        $titles = collect($res['data'])->pluck('title')->all();

        $this->assertContains('Visible', $titles);
        $this->assertContains('Solo usuarios', $titles);
        $this->assertNotContains('Oculto', $titles);
    }

    public function test_public_calendar_filters_by_affects_including_todos(): void
    {
        $this->event(['title' => 'Para interinos', 'affects' => 'interinos', 'visibility' => 'public', 'is_confirmed' => true, 'event_date' => '2030-03-01']);
        $this->event(['title' => 'Para todos', 'affects' => 'todos', 'visibility' => 'public', 'is_confirmed' => true, 'event_date' => '2030-03-02']);
        $this->event(['title' => 'Para funcionarios', 'affects' => 'funcionarios', 'visibility' => 'public', 'is_confirmed' => true, 'event_date' => '2030-03-03']);

        Sanctum::actingAs(User::factory()->create());

        $titles = collect($this->getJson('/api/v1/calendar?affects=interinos')->assertOk()->json('data'))->pluck('title')->all();

        $this->assertContains('Para interinos', $titles);
        $this->assertContains('Para todos', $titles);
        $this->assertNotContains('Para funcionarios', $titles);
    }

    public function test_published_documents_endpoint(): void
    {
        $source = \App\Models\MonitoredSource::create(['name' => 'S', 'url' => 'https://s.test', 'type' => 'gva']);
        \App\Models\DetectedDocument::create([
            'source_id' => $source->id, 'title' => 'Publicado', 'document_type' => 'vacantes',
            'status' => 'published', 'published_at' => now(),
        ]);
        \App\Models\DetectedDocument::create([
            'source_id' => $source->id, 'title' => 'Pendiente', 'document_type' => 'vacantes', 'status' => 'pending',
        ]);

        Sanctum::actingAs(User::factory()->create());

        $res = $this->getJson('/api/v1/published-documents')->assertOk()->json();
        $this->assertCount(1, $res['data']);
        $this->assertSame('Publicado', $res['data'][0]['title']);
    }
}
