<?php

namespace Tests\Feature;

use App\Models\Ccaa;
use App\Models\Centro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentroCaracteristicaFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_filter_by_jornada_continua_and_badge_exposed(): void
    {
        $cv = Ccaa::create(['code' => 'CV', 'name' => 'CV', 'is_active' => true]);

        Centro::create(['ccaa_id' => $cv->id, 'codigo' => '46000001', 'nombre' => 'CEIP Continu', 'localidad' => 'X', 'provincia' => 'València', 'tipo' => 'CEIP', 'caracteristicas' => ['JORNADA_CONTINUA']]);
        Centro::create(['ccaa_id' => $cv->id, 'codigo' => '46000002', 'nombre' => 'IES Partida', 'localidad' => 'Y', 'provincia' => 'València', 'tipo' => 'IES', 'caracteristicas' => []]);

        // Sin filtro: ambos; y el card expone caracteristicas.
        $this->getJson('/api/v1/centros')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.caracteristicas', fn ($v) => is_array($v));

        // Filtrado por jornada continua: solo el CEIP.
        $res = $this->getJson('/api/v1/centros?caracteristica=JORNADA_CONTINUA')->assertOk();
        $res->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.codigo', '46000001')
            ->assertJsonPath('data.0.caracteristicas.0', 'JORNADA_CONTINUA');
    }
}
