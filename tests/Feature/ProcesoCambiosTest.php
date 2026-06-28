<?php

namespace Tests\Feature;

use App\Models\Ccaa;
use App\Models\Colectivo;
use App\Models\Proceso;
use App\Models\ProcesoImportacion;
use App\Models\Specialty;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcesoCambiosTest extends TestCase
{
    use RefreshDatabase;

    private function makeProceso(): Proceso
    {
        $cv = Ccaa::create(['code' => 'CV', 'name' => 'CV', 'is_active' => true]);
        $col = Colectivo::create(['ccaa_id' => $cv->id, 'code' => 'INTERINO', 'name' => 'Interins', 'body' => 'SECUNDARIA']);

        return Proceso::create([
            'ccaa_id' => $cv->id, 'colectivo_id' => $col->id, 'anyo' => 2026, 'curso' => '2026-2027',
            'nombre' => 'Interins Secundària 2026-2027', 'estado' => 'publicado',
        ]);
    }

    public function test_cambios_endpoint_reports_no_changes_without_imports(): void
    {
        $proceso = $this->makeProceso();

        $this->getJson("/api/v1/procesos/{$proceso->id}/cambios")
            ->assertOk()
            ->assertJsonPath('has_changes', false)
            ->assertJsonPath('nuevas', 0);
    }

    public function test_cambios_endpoint_ignores_first_import(): void
    {
        $proceso = $this->makeProceso();

        ProcesoImportacion::create([
            'proceso_id' => $proceso->id,
            'importado_en' => now(),
            'total' => 10,
            'nuevas' => 0,
            'modificadas' => 0,
            'eliminadas' => 0,
            'es_primera' => true,
        ]);

        $this->getJson("/api/v1/procesos/{$proceso->id}/cambios")
            ->assertOk()
            ->assertJsonPath('has_changes', false);
    }

    public function test_cambios_endpoint_reports_latest_changes(): void
    {
        $proceso = $this->makeProceso();

        ProcesoImportacion::create([
            'proceso_id' => $proceso->id, 'importado_en' => now()->subDay(),
            'total' => 8, 'nuevas' => 0, 'modificadas' => 0, 'eliminadas' => 0, 'es_primera' => true,
        ]);
        ProcesoImportacion::create([
            'proceso_id' => $proceso->id, 'importado_en' => now(),
            'total' => 10, 'nuevas' => 3, 'modificadas' => 2, 'eliminadas' => 1, 'es_primera' => false,
        ]);

        $this->getJson("/api/v1/procesos/{$proceso->id}/cambios")
            ->assertOk()
            ->assertJsonPath('has_changes', true)
            ->assertJsonPath('nuevas', 3)
            ->assertJsonPath('modificadas', 2)
            ->assertJsonPath('eliminadas', 1);
    }

    public function test_vacancy_exposes_cambio_flag_in_api(): void
    {
        $cv = Ccaa::create(['code' => 'CV', 'name' => 'CV', 'is_active' => true]);
        $col = Colectivo::create(['ccaa_id' => $cv->id, 'code' => 'INTERINO', 'name' => 'Interins', 'body' => 'SECUNDARIA']);
        $spec = Specialty::create(['code' => '218', 'codigo' => '218', 'name' => 'Orientació', 'body' => 'PES', 'education_level' => 'secundaria', 'cuerpo' => 'SECUNDARIA', 'ccaa_id' => $cv->id]);
        $proceso = Proceso::create([
            'ccaa_id' => $cv->id, 'colectivo_id' => $col->id, 'anyo' => 2026, 'curso' => '2026-2027',
            'nombre' => 'Interins Secundària 2026-2027', 'estado' => 'publicado',
        ]);

        Vacancy::create(['specialty_id' => $spec->id, 'proceso_id' => $proceso->id, 'ccaa_id' => $cv->id, 'num' => 1, 'provincia' => 'València', 'localidad' => 'VALÈNCIA', 'centro_codigo' => '46000001', 'centro_nombre' => 'IES A', 'tipo_centro' => 'Secundaria', 'lloc' => '900001', 'year' => 2026, 'requisito_linguistico' => false, 'itinerante' => false, 'cambio' => 'nueva']);

        $this->getJson("/api/v1/procesos/{$proceso->id}/vacantes")
            ->assertOk()
            ->assertJsonPath('data.0.cambio', 'nueva');
    }
}
