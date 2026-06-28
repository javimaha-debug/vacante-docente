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

class VacancyResourceFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_vacancy_exposes_itinerante_and_enriched_centre_tags(): void
    {
        $cv = Ccaa::create(['code' => 'CV', 'name' => 'CV', 'is_active' => true]);
        $col = Colectivo::create(['ccaa_id' => $cv->id, 'code' => 'INTERINO', 'name' => 'Interins', 'body' => 'SECUNDARIA']);
        $spec = Specialty::create(['code' => '218', 'codigo' => '218', 'name' => 'Orientació', 'body' => 'PES', 'education_level' => 'secundaria', 'cuerpo' => 'SECUNDARIA', 'ccaa_id' => $cv->id]);
        $proceso = Proceso::create([
            'ccaa_id' => $cv->id, 'colectivo_id' => $col->id, 'anyo' => 2026, 'curso' => '2026-2027',
            'nombre' => 'Interins 2026-2027', 'estado' => 'publicado',
        ]);

        Centro::create(['ccaa_id' => $cv->id, 'codigo' => '46000010', 'nombre' => 'CIPFP Costa', 'localidad' => 'X', 'provincia' => 'València', 'tipo' => 'CIPFP', 'caracteristicas' => ['EDUCACIO_ESPECIAL', 'FPA']]);

        Vacancy::create([
            'specialty_id' => $spec->id, 'proceso_id' => $proceso->id, 'ccaa_id' => $cv->id, 'num' => 1,
            'provincia' => 'València', 'localidad' => 'X', 'centro_codigo' => '46000010', 'centro_nombre' => 'CIPFP Costa',
            'tipo_centro' => 'Secundaria', 'lloc' => '900001', 'year' => 2026, 'requisito_linguistico' => false, 'itinerante' => true,
        ]);

        $res = $this->getJson("/api/v1/procesos/{$proceso->id}/vacantes")->assertOk();

        $res->assertJsonPath('data.0.itinerante', true);

        $tags = $res->json('data.0.observ_tags');
        $this->assertContains('CEE', $tags);
        $this->assertContains('FPA', $tags);
        $this->assertContains('CIPFP', $tags); // derived from the centre name
    }
}
