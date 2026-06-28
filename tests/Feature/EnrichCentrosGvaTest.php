<?php

namespace Tests\Feature;

use App\Models\Ccaa;
use App\Models\Centro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    public function test_enrich_gva_populates_contact_fields(): void
    {
        $centro = $this->makeCentro();

        Http::fake([
            'www.ceice.gva.es/*' => Http::response([
                'denominacioCompleta' => 'IES Oficial',
                'domicilio' => 'C/ Major, 1',
                'telefon' => '961112233',
                'email' => 'info@iesoficial.es',
                'web' => 'www.iesoficial.es',
                'localitat' => 'València',
            ]),
        ]);

        $this->artisan('centros:enrich-gva', ['--codigo' => '46003251', '--sleep' => 0])
            ->assertSuccessful();

        $centro->refresh();
        $this->assertSame('961112233', $centro->telefono);
        $this->assertSame('info@iesoficial.es', $centro->email);
        $this->assertSame('www.iesoficial.es', $centro->web);
        $this->assertSame('C/ Major, 1', $centro->direccion_oficial);
    }

    public function test_enrich_gva_counts_not_found(): void
    {
        $this->makeCentro();

        Http::fake([
            'www.ceice.gva.es/*' => Http::response(null, 404),
        ]);

        // A 404 / empty directory record leaves the centro untouched.
        $this->artisan('centros:enrich-gva', ['--codigo' => '46003251', '--sleep' => 0])
            ->assertSuccessful();

        $this->assertNull($this->makeCentro(['codigo' => '99999999'])->fresh()->direccion_oficial);
    }

    public function test_centro_detail_exposes_official_fields(): void
    {
        $this->makeCentro([
            'web' => 'https://iesoficial.es',
            'direccion_oficial' => 'C/ Major, 1',
            'telefono' => '961112233',
            'email' => 'info@iesoficial.es',
        ]);

        $this->getJson('/api/v1/centros/46003251')
            ->assertOk()
            ->assertJsonPath('centro.web', 'https://iesoficial.es')
            ->assertJsonPath('centro.direccion_oficial', 'C/ Major, 1')
            ->assertJsonPath('centro.telefono', '961112233')
            ->assertJsonPath('centro.email', 'info@iesoficial.es');
    }
}
