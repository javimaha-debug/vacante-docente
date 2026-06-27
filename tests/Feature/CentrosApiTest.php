<?php

namespace Tests\Feature;

use App\Console\Commands\ImportCentros;
use App\Models\Ccaa;
use App\Models\Centro;
use App\Models\CentroHorario;
use App\Models\CentroValoracion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CentrosApiTest extends TestCase
{
    use RefreshDatabase;

    private Ccaa $cv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cv = Ccaa::create(['code' => 'CV', 'name' => 'CV', 'is_active' => true]);
    }

    private function makeCentro(array $attrs = []): Centro
    {
        return Centro::create(array_merge([
            'ccaa_id' => $this->cv->id,
            'codigo' => '46011223',
            'nombre' => 'IES LA FONT',
            'tipo' => 'IES',
            'localidad' => 'València',
            'provincia' => 'València',
            'telefono' => '961234567',
            'latitude' => 39.47,
            'longitude' => -0.37,
        ], $attrs));
    }

    public function test_index_filters_and_paginates(): void
    {
        $this->makeCentro();
        $this->makeCentro(['codigo' => '03000001', 'nombre' => 'CEIP SOL', 'tipo' => 'CEIP', 'localidad' => 'Alacant', 'provincia' => 'Alacant', 'latitude' => 38.34, 'longitude' => -0.48]);

        $this->getJson('/api/v1/centros')->assertOk()->assertJsonPath('total', 2);
        $this->getJson('/api/v1/centros?tipo=IES')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/v1/centros?query=SOL')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/v1/centros?provincia=Alacant')->assertOk()->assertJsonPath('total', 1);
    }

    public function test_proximity_search_returns_distance_sorted(): void
    {
        $this->makeCentro(); // València
        $this->makeCentro(['codigo' => '03000001', 'nombre' => 'CEIP SOL', 'localidad' => 'Alacant', 'latitude' => 38.34, 'longitude' => -0.48]);

        $res = $this->getJson('/api/v1/centros?lat=39.47&lng=-0.37')->assertOk();
        $data = $res->json('data');
        $this->assertNotNull($data[0]['distance_km']);
        $this->assertSame('46011223', $data[0]['codigo']); // closest first
    }

    public function test_show_returns_detail_with_aggregates(): void
    {
        $centro = $this->makeCentro();
        $u = User::factory()->create();
        CentroValoracion::create(['centro_id' => $centro->id, 'user_id' => $u->id, 'puntuacion' => 4, 'ambiente_trabajo' => 5, 'curso_escolar' => '2025-2026', 'comentario' => 'Bé']);

        $this->getJson('/api/v1/centros/46011223')
            ->assertOk()
            ->assertJsonPath('centro.codigo', '46011223')
            ->assertJsonPath('valoraciones.count', 1)
            ->assertJsonPath('valoraciones.puntuacion', 4);
    }

    public function test_horario_dedupe_increments_validations(): void
    {
        $centro = $this->makeCentro();
        $a = User::factory()->create();
        $b = User::factory()->create();

        $body = ['hora_entrada' => '08:00', 'hora_salida' => '15:00', 'curso_escolar' => '2025-2026', 'jornada_continua' => true];

        Sanctum::actingAs($a);
        $this->postJson('/api/v1/centros/46011223/horarios', $body)->assertCreated();

        Sanctum::actingAs($b);
        $this->postJson('/api/v1/centros/46011223/horarios', $body)->assertOk();

        $this->assertSame(1, CentroHorario::where('centro_id', $centro->id)->count());
        $this->assertSame(2, CentroHorario::where('centro_id', $centro->id)->first()->validaciones);
    }

    public function test_valoracion_upserts_per_user_curso(): void
    {
        $centro = $this->makeCentro();
        $u = User::factory()->create();
        Sanctum::actingAs($u);

        $this->postJson('/api/v1/centros/46011223/valoraciones', ['puntuacion' => 3, 'curso_escolar' => '2025-2026'])->assertCreated();
        $this->postJson('/api/v1/centros/46011223/valoraciones', ['puntuacion' => 5, 'curso_escolar' => '2025-2026'])->assertCreated();

        $this->assertSame(1, CentroValoracion::where('centro_id', $centro->id)->count());
        $this->assertSame(5, CentroValoracion::where('centro_id', $centro->id)->first()->puntuacion);
    }

    public function test_csv_parser_maps_aliased_headers(): void
    {
        $csv = "codi;denominacio;tipus;localitat;provincia;telefon\n"
            ."46011223;IES LA FONT;IES;València;València;961234567\n"
            ."03000001;CEIP SOL;CEIP;Alacant;Alacant;965000000\n";

        $rows = (new ImportCentros())->parseCsv($csv);

        $this->assertCount(2, $rows);
        $this->assertSame('46011223', $rows[0]['codigo']);
        $this->assertSame('IES LA FONT', $rows[0]['nombre']);
        $this->assertSame('València', $rows[0]['localidad']);
    }
}
