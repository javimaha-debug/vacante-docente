<?php

namespace Tests\Feature;

use App\Models\Ccaa;
use App\Models\Centro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrichCentrosGvaTest extends TestCase
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
            'codigo' => '46003251',
            'nombre' => 'IES Test',
            'tipo' => 'IES',
            'localidad' => 'València',
            'provincia' => 'València',
        ], $attrs));
    }

    /** Write a minimal GVA-format CSV fixture and return its path. */
    private function fixtureCsv(): string
    {
        $header = 'codigo;denominacion;regimen;tipo_via;direccion;numero;codigo_postal;localidad;provincia;telefono;longitud;latitud;url_es;url_va';
        $row = '"46003251";"IES Oficial";"PÚB.";"CARRER";"MAJOR";"1";"46001";"VALÈNCIA";"VALENCIA";"961112233";"-0.37610";"39.47020";"https://www.ceice.gva.es/web/centros-docentes/ficha-centro?codi=46003251";"https://www.ceice.gva.es/va/web/centros-docentes/ficha-centro?codi=46003251"';
        $path = tempnam(sys_get_temp_dir(), 'gva').'.csv';
        file_put_contents($path, $header."\n".$row."\n");

        return $path;
    }

    public function test_enrich_gva_populates_contact_fields_from_csv(): void
    {
        $centro = $this->makeCentro();
        $csv = $this->fixtureCsv();

        $this->artisan('centros:enrich-gva', ['--file' => $csv, '--codigo' => '46003251'])
            ->assertSuccessful();

        $centro->refresh();
        $this->assertSame('961112233', $centro->telefono);
        $this->assertSame('https://www.ceice.gva.es/web/centros-docentes/ficha-centro?codi=46003251', $centro->web);
        $this->assertSame('CARRER MAJOR, 1, 46001 VALÈNCIA', $centro->direccion_oficial);
        $this->assertEqualsWithDelta(39.47020, (float) $centro->latitude, 0.0001);

        @unlink($csv);
    }

    public function test_enrich_gva_does_not_overwrite_existing_coordinates(): void
    {
        $centro = $this->makeCentro(['latitude' => 39.0, 'longitude' => -0.5]);
        $csv = $this->fixtureCsv();

        $this->artisan('centros:enrich-gva', ['--file' => $csv, '--codigo' => '46003251'])
            ->assertSuccessful();

        $centro->refresh();
        $this->assertEqualsWithDelta(39.0, (float) $centro->latitude, 0.0001, 'verified coords must be preserved');

        @unlink($csv);
    }

    public function test_enrich_gva_counts_centros_without_a_csv_match(): void
    {
        $this->makeCentro(['codigo' => '99999999']);
        $csv = $this->fixtureCsv();

        $this->artisan('centros:enrich-gva', ['--file' => $csv, '--codigo' => '99999999'])
            ->expectsOutputToContain('Sin coincidencia en el CSV: 1')
            ->assertSuccessful();

        @unlink($csv);
    }

    public function test_enrich_gva_updates_every_matching_centro(): void
    {
        // Regression: a previous chunkById + orderBy('codigo') combination only
        // processed a subset. Every matching centro must be enriched.
        $header = 'codigo;tipo_via;direccion;numero;codigo_postal;localidad;telefono;longitud;latitud;url_es;url_va';
        $rows = [];
        $codes = [];
        for ($i = 1; $i <= 25; $i++) {
            $code = sprintf('4600%04d', $i);
            $codes[] = $code;
            $rows[] = "\"{$code}\";\"CARRER\";\"X\";\"{$i}\";\"46001\";\"VALÈNCIA\";\"96100{$i}\";\"-0.3\";\"39.4\";\"https://gva.es/c/{$code}\";\"https://gva.es/va/{$code}\"";
            Centro::create([
                'ccaa_id' => $this->cv->id, 'codigo' => $code, 'nombre' => "C{$i}",
                'tipo' => 'IES', 'localidad' => 'València', 'provincia' => 'València',
            ]);
        }
        $path = tempnam(sys_get_temp_dir(), 'gva').'.csv';
        file_put_contents($path, $header."\n".implode("\n", $rows)."\n");

        $this->artisan('centros:enrich-gva', ['--file' => $path])
            ->expectsOutputToContain('Actualizados: 25')
            ->assertSuccessful();

        $this->assertSame(0, Centro::whereIn('codigo', $codes)->whereNull('web')->count(),
            'every matching centro should have its web populated');

        @unlink($path);
    }

    public function test_missing_csv_file_fails_cleanly(): void
    {
        $this->makeCentro();

        $this->artisan('centros:enrich-gva', ['--file' => '/no/such/file.csv'])
            ->expectsOutputToContain('No se encuentra el CSV')
            ->assertFailed();
    }

    public function test_centro_detail_exposes_official_fields(): void
    {
        $this->makeCentro([
            'web' => 'https://iesoficial.es',
            'direccion_oficial' => 'C/ Major, 1',
            'telefono' => '961112233',
        ]);

        $this->getJson('/api/v1/centros/46003251')
            ->assertOk()
            ->assertJsonPath('centro.web', 'https://iesoficial.es')
            ->assertJsonPath('centro.direccion_oficial', 'C/ Major, 1')
            ->assertJsonPath('centro.telefono', '961112233');
    }
}
