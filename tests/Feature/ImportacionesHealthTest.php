<?php

namespace Tests\Feature;

use App\Models\GvaNoticia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ImportacionesHealthTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'superadmin'])->save();

        return $admin->fresh();
    }

    public function test_health_requires_superadmin(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/superadmin/importaciones/health')->assertForbidden();
    }

    public function test_health_returns_pipeline_snapshot(): void
    {
        GvaNoticia::create([
            'titulo' => 'Llistat vacants', 'url' => 'https://ceice.gva.es/x.pdf', 'tipo' => 'PDF',
            'import_estado' => 'ok', 'importado_en' => now(),
        ]);
        GvaNoticia::create([
            'titulo' => 'Pendiente', 'url' => 'https://ceice.gva.es/y.pdf', 'tipo' => 'PDF',
        ]);

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/v1/superadmin/importaciones/health')
            ->assertOk()
            ->assertJsonPath('resumen.total', 2)
            ->assertJsonPath('resumen.importadas', 1)
            ->assertJsonPath('resumen.pendientes', 1)
            ->assertJsonStructure(['resumen' => ['ultima_deteccion', 'errores', 'sin_proceso'], 'noticias', 'importaciones']);
    }

    public function test_run_monitor_executes_and_returns_health(): void
    {
        // Stub every outbound GVA request so the monitor runs offline.
        Http::fake(['*' => Http::response('<html></html>', 200)]);

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/superadmin/importaciones/run-monitor')
            ->assertOk()
            ->assertJsonPath('ran', true)
            ->assertJsonStructure(['ran', 'output', 'health' => ['resumen', 'noticias', 'importaciones']]);
    }
}
