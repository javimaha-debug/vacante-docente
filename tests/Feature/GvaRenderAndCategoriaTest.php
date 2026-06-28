<?php

namespace Tests\Feature;

use App\Jobs\MonitorGvaJob;
use App\Services\GvaRenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GvaRenderAndCategoriaTest extends TestCase
{
    use RefreshDatabase;

    public function test_render_service_returns_null_when_disabled(): void
    {
        config(['gva.render.enabled' => false]);

        $this->assertNull(app(GvaRenderService::class)->pdfLinks('https://x.test/p'));
    }

    public function test_render_service_returns_null_when_no_urls(): void
    {
        config(['gva.render.enabled' => true]);

        $this->assertNull(app(GvaRenderService::class)->pdfLinks());
    }

    public function test_static_parser_tags_continua_pdfs(): void
    {
        $job = new MonitorGvaJob();
        $html = '<a href="https://ceice.gva.es/documents/1/2/260602_lis_sec.pdf">Contínua sec</a>'
            .'<a href="https://ceice.gva.es/documents/1/2/260602_pue_def.pdf">Llocs oferts</a>';

        $noticias = $job->parsePdfLinks($html, 'https://ceice.gva.es/x');

        $continua = collect($noticias)->firstWhere('url', 'https://ceice.gva.es/documents/1/2/260602_lis_sec.pdf');
        $otro = collect($noticias)->firstWhere('url', 'https://ceice.gva.es/documents/1/2/260602_pue_def.pdf');

        $this->assertSame('continua', $continua['categoria']);
        $this->assertNull($otro['categoria']);
    }

    public function test_rendered_noticias_empty_when_render_unavailable(): void
    {
        config(['gva.render.enabled' => false]);

        $this->assertSame([], (new MonitorGvaJob())->fetchRenderedNoticias('https://ceice.gva.es/inicio', 'inicio'));
    }
}
