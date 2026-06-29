<?php

namespace Tests\Feature;

use App\Models\AcademicCalendarEvent;
use App\Models\DetectedDocument;
use App\Models\MonitoredSource;
use App\Models\User;
use App\Notifications\DocumentosDetectados;
use App\Services\DocumentMonitorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentMonitorTest extends TestCase
{
    use RefreshDatabase;

    private function source(array $attrs = []): MonitoredSource
    {
        return MonitoredSource::create(array_merge([
            'name' => 'Fuente test', 'url' => 'https://example.test/listados', 'type' => 'gva', 'active' => true,
        ], $attrs));
    }

    public function test_scan_detects_pdf_and_article_links_and_suggests_events(): void
    {
        Notification::fake();
        Storage::fake('local');

        $source = $this->source();

        $html = <<<HTML
        <html><body>
            <a href="/docs/llistat_provisional_interins.pdf">Llistat provisional de participants</a>
            <a href="https://example.test/noticia/adjudicacio-inici">Adjudicació inici de curs 2026</a>
            <a href="/docs/manual-uso.pdf">Manual de uso del portal</a>
            <a href="/contacto">Contacto</a>
        </body></html>
        HTML;

        Http::fake([
            'example.test/listados' => Http::response($html, 200),
            'example.test/docs/*' => Http::response('%PDF-1.4 fake', 200),
        ]);

        $result = app(DocumentMonitorService::class)->scan($source);

        // The provisional PDF + the adjudicació article are relevant; the generic
        // "manual de uso" PDF and the "contacto" link are not.
        $this->assertSame(2, $result['nuevos']);
        $this->assertDatabaseHas('detected_documents', [
            'source_id' => $source->id,
            'document_type' => 'listado_provisional',
            'status' => 'pending',
        ]);
        $this->assertDatabaseMissing('detected_documents', ['title' => 'Manual de uso del portal']);

        // Provisional → suggested listado_provisional event; adjudicació → adjudicacion.
        $this->assertSame(2, $result['eventos']);
        $this->assertDatabaseHas('academic_calendar_events', [
            'event_type' => 'listado_provisional', 'is_confirmed' => false, 'visibility' => 'superadmin_only',
        ]);

        $this->assertNotNull($source->fresh()->last_checked_at);
    }

    public function test_scan_is_idempotent(): void
    {
        Storage::fake('local');
        $source = $this->source();
        $html = '<a href="/d/llistat_definitiu.pdf">Llistat definitiu de participants</a>';
        Http::fake([
            'example.test/listados' => Http::response($html, 200),
            'example.test/d/*' => Http::response('%PDF', 200),
        ]);

        $service = app(DocumentMonitorService::class);
        $service->scan($source);
        $second = $service->scan($source);

        $this->assertSame(0, $second['nuevos']);
        $this->assertSame(1, DetectedDocument::where('source_id', $source->id)->count());
    }

    public function test_command_notifies_superadmins_when_new_docs(): void
    {
        Notification::fake();
        Storage::fake('local');
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'superadmin'])->save();

        $this->source();
        Http::fake([
            'example.test/listados' => Http::response('<a href="/x/llistat_provisional.pdf">Llistat provisional interins</a>', 200),
            'example.test/x/*' => Http::response('%PDF', 200),
        ]);

        $this->artisan('documents:monitor')->assertSuccessful();

        Notification::assertSentTo($admin, DocumentosDetectados::class);
    }

    public function test_classify_maps_keywords_to_types(): void
    {
        $s = app(DocumentMonitorService::class);
        $this->assertSame('listado_provisional', $s->classify('llistat provisional de participants'));
        $this->assertSame('listado_definitivo', $s->classify('llistat definitiu'));
        $this->assertSame('vacantes', $s->classify('vacants secundaria'));
        $this->assertSame('resolucion', $s->classify('resolucio de la direccio'));
        $this->assertSame('otro', $s->classify('nota informativa'));
    }
}
