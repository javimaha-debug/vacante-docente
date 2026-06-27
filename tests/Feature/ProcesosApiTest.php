<?php

namespace Tests\Feature;

use App\Models\Ccaa;
use App\Models\Colectivo;
use App\Models\Proceso;
use App\Models\Specialty;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcesosApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeProceso(): array
    {
        $cv = Ccaa::create(['code' => 'CV', 'name' => 'CV', 'is_active' => true]);
        $col = Colectivo::create(['ccaa_id' => $cv->id, 'code' => 'SUPRIMIDO', 'name' => 'Suprimits', 'body' => 'SECUNDARIA']);
        $spec = Specialty::create(['code' => '218', 'codigo' => '218', 'name' => 'Orientació', 'body' => 'PES', 'education_level' => 'secundaria', 'ccaa_id' => $cv->id]);
        $proceso = Proceso::create([
            'ccaa_id' => $cv->id, 'colectivo_id' => $col->id, 'anyo' => 2026, 'curso' => '2026-2027',
            'nombre' => 'Suprimits Secundària 2026-2027', 'estado' => 'publicado',
        ]);

        Vacancy::create(['specialty_id' => $spec->id, 'proceso_id' => $proceso->id, 'ccaa_id' => $cv->id, 'num' => 1, 'provincia' => 'València', 'localidad' => 'VALÈNCIA', 'centro_codigo' => '46000001', 'centro_nombre' => 'IES A', 'tipo_centro' => 'Secundaria', 'lloc' => '900001', 'year' => 2026, 'requisito_linguistico' => true, 'itinerante' => false]);
        Vacancy::create(['specialty_id' => $spec->id, 'proceso_id' => $proceso->id, 'ccaa_id' => $cv->id, 'num' => 2, 'provincia' => 'Alacant', 'localidad' => 'ALACANT', 'centro_codigo' => '03000002', 'centro_nombre' => 'CEIP B', 'tipo_centro' => 'Primaria/Infantil', 'lloc' => '900002', 'year' => 2026, 'requisito_linguistico' => false, 'itinerante' => true]);

        return compact('proceso', 'spec');
    }

    public function test_procesos_index_includes_vacancy_count(): void
    {
        $this->makeProceso();

        $this->getJson('/api/v1/procesos')
            ->assertOk()
            ->assertJsonPath('data.0.estado', 'publicado')
            ->assertJsonPath('data.0.vacancies_count', 2)
            ->assertJsonPath('data.0.colectivo.code', 'SUPRIMIDO');
    }

    public function test_proceso_vacantes_filters(): void
    {
        ['proceso' => $proceso] = $this->makeProceso();

        $this->getJson("/api/v1/procesos/{$proceso->id}/vacantes")
            ->assertOk()->assertJsonCount(2, 'data');

        $this->getJson("/api/v1/procesos/{$proceso->id}/vacantes?provincia=Alacant")
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.provincia', 'Alacant');

        $this->getJson("/api/v1/procesos/{$proceso->id}/vacantes?req_ling=1")
            ->assertOk()->assertJsonCount(1, 'data');

        $this->getJson("/api/v1/procesos/{$proceso->id}/vacantes?itinerante=1")
            ->assertOk()->assertJsonCount(1, 'data');
    }
}
