<?php

namespace Tests\Feature;

use App\Models\Ccaa;
use App\Models\Centro;
use App\Models\Colectivo;
use App\Models\Proceso;
use App\Models\Specialty;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VacancyTagFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_cra_filter_only_returns_vacancies_in_cra_centres(): void
    {
        $cv = Ccaa::create(['code' => 'CV', 'name' => 'CV', 'is_active' => true]);
        $col = Colectivo::create(['ccaa_id' => $cv->id, 'code' => 'SUPRIMIDO', 'name' => 'S', 'body' => 'SECUNDARIA']);
        $spec = Specialty::create(['code' => '218', 'codigo' => '218', 'name' => 'Orientació', 'body' => 'PES', 'education_level' => 'secundaria', 'ccaa_id' => $cv->id]);
        $proceso = Proceso::create(['ccaa_id' => $cv->id, 'colectivo_id' => $col->id, 'anyo' => 2026, 'curso' => '2026-2027', 'nombre' => 'P', 'estado' => 'publicado']);

        // One CRA centre, one ordinary centre.
        Centro::create(['ccaa_id' => $cv->id, 'codigo' => '46000001', 'nombre' => 'CRA Exemple', 'localidad' => 'X', 'provincia' => 'València', 'tipo' => 'CRA', 'caracteristicas' => ['CRA']]);
        Centro::create(['ccaa_id' => $cv->id, 'codigo' => '46000002', 'nombre' => 'IES Normal', 'localidad' => 'Y', 'provincia' => 'València', 'tipo' => 'Secundaria', 'caracteristicas' => []]);

        $craVacancy = Vacancy::create([
            'specialty_id' => $spec->id, 'proceso_id' => $proceso->id, 'ccaa_id' => $cv->id,
            'num' => 1, 'provincia' => 'València', 'localidad' => 'X', 'centro_codigo' => '46000001',
            'centro_nombre' => 'CRA Exemple', 'tipo_centro' => 'Primaria/Infantil', 'lloc' => '900001', 'year' => 2026,
        ]);
        Vacancy::create([
            'specialty_id' => $spec->id, 'proceso_id' => $proceso->id, 'ccaa_id' => $cv->id,
            'num' => 2, 'provincia' => 'València', 'localidad' => 'Y', 'centro_codigo' => '46000002',
            'centro_nombre' => 'IES Normal', 'tipo_centro' => 'Secundaria', 'lloc' => '900002', 'year' => 2026,
        ]);

        // Without the filter: both show.
        $this->getJson("/api/v1/procesos/{$proceso->id}/vacantes?especialidad={$spec->id}")
            ->assertOk()->assertJsonCount(2, 'data');

        // With CRA: only the one in the CRA centre, and it carries the badge.
        $this->getJson("/api/v1/procesos/{$proceso->id}/vacantes?especialidad={$spec->id}&tags[]=CRA")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $craVacancy->id)
            ->assertJsonPath('data.0.observ_tags.0', 'CRA');
    }
}
