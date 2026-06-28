<?php

namespace Tests\Feature;

use App\Models\Ccaa;
use App\Models\Centro;
use App\Services\GoogleMapsService;
use App\Services\GvaCentrosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CentrosEnrichTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrich_maps_gva_payload_and_persists(): void
    {
        $cv = Ccaa::create(['code' => 'CV', 'name' => 'CV', 'is_active' => true]);
        $centro = Centro::create([
            'ccaa_id' => $cv->id, 'codigo' => '46016521', 'nombre' => 'Antiguo', 'tipo' => 'IES',
            'localidad' => '', 'provincia' => 'València', 'caracteristicas' => [],
        ]);

        // The mapping is tolerant to field names; a representative GVA-ish shape.
        Http::fake([
            'ceice.gva.es/*' => Http::response([
                'centro' => [
                    'denominacion' => 'IES El Clot',
                    'domicilio' => "Carrer Major, 1",
                    'telefon' => '961234567',
                    'correu' => 'centro@gva.es',
                    'web' => 'https://ieselclot.edu.gva.es',
                    'localitat' => 'València',
                    'provincia' => 'València',
                    'latitud' => 39.47,
                    'longitud' => -0.37,
                ],
            ]),
        ]);

        $service = new GvaCentrosService(new GoogleMapsService('test-key'));
        $res = $service->enrich($centro->fresh());

        $this->assertSame('updated', $res['status']);
        $this->assertFalse($res['geocoded']); // coords came from the API
        $centro->refresh();
        $this->assertSame('IES El Clot', $centro->nombre);
        $this->assertSame('Carrer Major, 1', $centro->direccion);
        $this->assertSame('961234567', $centro->telefono);
        $this->assertSame('centro@gva.es', $centro->email);
        $this->assertSame('València', $centro->localidad);
        $this->assertEqualsWithDelta(39.47, (float) $centro->latitude, 0.001);
        $this->assertTrue($centro->datos_verificados);
        $this->assertSame('GVA', $centro->fuente);
    }

    public function test_enrich_geocodes_when_api_has_no_coords(): void
    {
        $cv = Ccaa::create(['code' => 'CV', 'name' => 'CV', 'is_active' => true]);
        $centro = Centro::create([
            'ccaa_id' => $cv->id, 'codigo' => '46000099', 'nombre' => 'X', 'tipo' => 'CEIP',
            'localidad' => 'Quart', 'provincia' => 'València', 'caracteristicas' => [],
        ]);

        Http::fake([
            'ceice.gva.es/*' => Http::response(['denominacion' => 'CEIP Test', 'domicilio' => 'Plaça 1', 'localitat' => 'Quart']),
            'maps.googleapis.com/maps/api/geocode/*' => Http::response([
                'status' => 'OK',
                'results' => [['formatted_address' => 'Plaça 1, Quart', 'geometry' => ['location' => ['lat' => 39.5, 'lng' => -0.45]]]],
            ]),
        ]);

        $res = (new GvaCentrosService(new GoogleMapsService('test-key')))->enrich($centro->fresh());

        $this->assertSame('updated', $res['status']);
        $this->assertTrue($res['geocoded']);
        $centro->refresh();
        $this->assertEqualsWithDelta(39.5, (float) $centro->latitude, 0.001);
    }
}
