<?php

namespace Tests\Feature;

use App\Jobs\MonitorGvaJob;
use App\Models\GvaNoticia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MonitorGvaJobTest extends TestCase
{
    use RefreshDatabase;

    private const RSS = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <rss version="2.0"><channel>
      <item>
        <title>Resolució adjudicació de places personal docent interí</title>
        <link>https://dogv.gva.es/datos/2026/06/01/pdf/doc1.html</link>
        <description>Adjudicació de vacants per a la borsa de Secundària.</description>
        <pubDate>Mon, 01 Jun 2026 09:00:00 +0200</pubDate>
      </item>
      <item>
        <title>Subvenció per a associacions culturals</title>
        <link>https://dogv.gva.es/datos/2026/06/02/pdf/doc2.html</link>
        <description>Ajudes no relacionades amb educació.</description>
        <pubDate>Tue, 02 Jun 2026 09:00:00 +0200</pubDate>
      </item>
      <item>
        <title>Llistat de vacantes de mestres</title>
        <link>https://dogv.gva.es/datos/2026/06/03/pdf/doc3.html</link>
        <description>Vacantes disponibles.</description>
        <pubDate>Wed, 03 Jun 2026 09:00:00 +0200</pubDate>
      </item>
    </channel></rss>
    XML;

    private const HTML = <<<'HTML'
    <html><body>
      <a href="/documents/adjudicacions-2026.pdf">Adjudicacions 2026</a>
      <a href="https://ceice.gva.es/docs/instruccions.pdf">Instruccions</a>
      <a href="/web/otra-pagina">No es PDF</a>
    </body></html>
    HTML;

    public function test_job_stores_only_keyword_matching_rss_items_and_pdfs(): void
    {
        Http::fake([
            'dogv.gva.es/portal/rss/*' => Http::response(self::RSS, 200, ['Content-Type' => 'application/rss+xml']),
            'ceice.gva.es/*' => Http::response(self::HTML, 200, ['Content-Type' => 'text/html']),
        ]);

        (new MonitorGvaJob())->handle();

        // 2 of 3 RSS items match keywords; the subvención item is filtered out.
        $this->assertSame(2, GvaNoticia::where('tipo', 'RSS')->count());
        $this->assertFalse(GvaNoticia::where('titulo', 'like', '%Subvenció%')->exists());

        // 2 PDF links found; the non-PDF anchor is ignored.
        $this->assertSame(2, GvaNoticia::where('tipo', 'PDF')->count());

        // Relative PDF URL resolved to absolute.
        $this->assertTrue(
            GvaNoticia::where('url', 'https://ceice.gva.es/documents/adjudicacions-2026.pdf')->exists()
        );

        $rss = GvaNoticia::where('tipo', 'RSS')->where('titulo', 'like', '%personal docent%')->first();
        $this->assertNotNull($rss);
        $this->assertContains('adjudicació', $rss->keywords_matched);
        $this->assertSame('2026-06-01', $rss->fecha_publicacion->toDateString());
    }

    public function test_job_is_idempotent_on_reruns(): void
    {
        Http::fake([
            'dogv.gva.es/portal/rss/*' => Http::response(self::RSS, 200),
            'ceice.gva.es/*' => Http::response(self::HTML, 200),
        ]);

        (new MonitorGvaJob())->handle();
        (new MonitorGvaJob())->handle();

        // No duplicates created on a second run (deduped by URL).
        $this->assertSame(4, GvaNoticia::count());
    }

    public function test_matched_keywords_is_case_insensitive(): void
    {
        $job = new MonitorGvaJob();

        $this->assertSame(['interino'], $job->matchedKeywords('Texto sobre INTERINO docente'));
        $this->assertSame([], $job->matchedKeywords('Nada relevante aquí'));
    }

    public function test_noticias_endpoint_returns_latest_first(): void
    {
        GvaNoticia::create(['titulo' => 'Vieja', 'url' => 'https://x/1', 'tipo' => 'RSS', 'fecha_publicacion' => '2026-01-01']);
        GvaNoticia::create(['titulo' => 'Nueva', 'url' => 'https://x/2', 'tipo' => 'RSS', 'fecha_publicacion' => '2026-06-01']);

        $this->getJson('/api/v1/gva/noticias')
            ->assertOk()
            ->assertJsonPath('data.0.titulo', 'Nueva')
            ->assertJsonPath('data.1.titulo', 'Vieja');
    }
}
