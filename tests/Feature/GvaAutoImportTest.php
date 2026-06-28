<?php

namespace Tests\Feature;

use App\Jobs\MonitorGvaJob;
use App\Models\Ccaa;
use App\Models\Colectivo;
use App\Models\GvaNoticia;
use App\Models\Proceso;
use App\Models\User;
use App\Notifications\ListadoImportadoAdmin;
use App\Services\GvaAutoImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GvaAutoImportTest extends TestCase
{
    use RefreshDatabase;

    private function makeProceso(string $code, string $body, string $nombre, int $anyo = 2026): Proceso
    {
        $cv = Ccaa::firstOrCreate(['code' => 'CV'], ['name' => 'CV', 'is_active' => true]);
        $col = Colectivo::create(['ccaa_id' => $cv->id, 'code' => $code, 'name' => $nombre, 'body' => $body]);

        return Proceso::create([
            'ccaa_id' => $cv->id, 'colectivo_id' => $col->id, 'anyo' => $anyo, 'curso' => $anyo.'-'.($anyo + 1),
            'nombre' => $nombre, 'estado' => 'publicado',
        ]);
    }

    public function test_resolve_target_maps_participant_pdf_to_proceso(): void
    {
        $proceso = $this->makeProceso('INTERINO', 'MAESTROS', 'Interins Mestres 2026-2027');

        $noticia = GvaNoticia::create([
            'titulo' => 'Llistat provisional participants',
            'url' => 'https://ceice.gva.es/docs/ini_2026_par_pro_int_lis_mae.pdf',
            'tipo' => 'PDF',
        ]);

        $target = app(GvaAutoImportService::class)->resolveTarget($noticia);

        $this->assertNotNull($target);
        $this->assertSame('participantes', $target['kind']);
        $this->assertSame($proceso->id, $target['proceso']->id);
    }

    public function test_resolve_target_returns_null_without_matching_proceso(): void
    {
        // No proceso created at all.
        $noticia = GvaNoticia::create([
            'titulo' => 'Vacants secundària',
            'url' => 'https://ceice.gva.es/docs/2026_vacants_int_sec.pdf',
            'tipo' => 'PDF',
        ]);

        $this->assertNull(app(GvaAutoImportService::class)->resolveTarget($noticia));
    }

    public function test_import_marks_sin_proceso_when_unmappable(): void
    {
        $noticia = GvaNoticia::create([
            'titulo' => 'Llistat participants',
            'url' => 'https://ceice.gva.es/docs/lis_par_2026.pdf', // no body/code tokens
            'tipo' => 'PDF',
        ]);

        app(GvaAutoImportService::class)->import($noticia);

        $this->assertSame('sin_proceso', $noticia->fresh()->import_estado);
        $this->assertNull($noticia->fresh()->importado_en);
    }

    public function test_monitor_auto_imports_and_notifies_admins(): void
    {
        Notification::fake();
        Storage::fake('local');
        config(['gva.auto_import' => true]);

        $this->makeProceso('INTERINO', 'MAESTROS', 'Interins Mestres 2026-2027');
        $admin = User::factory()->create(['is_admin' => true]);

        $html = '<html><body><a href="/docs/ini_2026_par_pro_int_lis_mae.pdf">Llistat participants mestres</a></body></html>';

        Http::fake([
            'dogv.gva.es/portal/rss/*' => Http::response('<rss version="2.0"><channel></channel></rss>', 200),
            'ceice.gva.es/va/web/*' => Http::response($html, 200, ['Content-Type' => 'text/html']),
            // PDF download (import will fail to parse, but the flow + notification must run).
            'ceice.gva.es/docs/*' => Http::response('%PDF-fake', 200, ['Content-Type' => 'application/pdf']),
        ]);

        (new MonitorGvaJob())->handle();

        $noticia = GvaNoticia::where('tipo', 'PDF')->first();
        $this->assertNotNull($noticia);
        $this->assertNotNull($noticia->import_estado); // attempted (ok or error)

        Notification::assertSentTo($admin, ListadoImportadoAdmin::class);
    }
}
