<?php

namespace Tests\Feature;

use App\Models\Ccaa;
use App\Models\Colectivo;
use App\Models\ParticipanteProceso;
use App\Models\Proceso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MisListadosTest extends TestCase
{
    use RefreshDatabase;

    private function proceso(): Proceso
    {
        $cv = Ccaa::create(['code' => 'CV', 'name' => 'CV', 'is_active' => true]);
        $col = Colectivo::create(['ccaa_id' => $cv->id, 'code' => 'INTERINO', 'name' => 'Interins', 'body' => 'SECUNDARIA']);

        return Proceso::create([
            'ccaa_id' => $cv->id, 'colectivo_id' => $col->id, 'anyo' => 2026,
            'curso' => '2026-2027', 'nombre' => 'Interins Secundària 2026-2027', 'estado' => 'publicado',
        ]);
    }

    public function test_requires_nombre_gva(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/user/mis-listados')
            ->assertOk()
            ->assertJsonPath('configured', false);
    }

    public function test_finds_user_position_across_listings(): void
    {
        $proceso = $this->proceso();
        $user = User::factory()->create();
        $user->forceFill(['nombre_gva' => 'GARCIA LOPEZ, ANA'])->save();

        ParticipanteProceso::create([
            'proceso_id' => $proceso->id, 'posicion' => 42, 'nombre_gva' => 'GARCIA LOPEZ, ANA',
            'estado' => 'Adjudicat', 'especialidad_codigo' => '590', 'centro_nombre' => 'IES TEST',
            'localitat' => 'VALÈNCIA', 'lloc_adjudicado' => '900001',
        ]);
        // Someone else — must not match.
        ParticipanteProceso::create([
            'proceso_id' => $proceso->id, 'posicion' => 7, 'nombre_gva' => 'OTRO NOMBRE, X',
            'estado' => 'No adjudicat', 'especialidad_codigo' => '590',
        ]);

        Sanctum::actingAs($user->fresh());

        $res = $this->getJson('/api/v1/user/mis-listados')
            ->assertOk()
            ->assertJsonPath('configured', true)
            ->assertJsonPath('nombre_gva', 'GARCIA LOPEZ, ANA')
            ->json();

        $this->assertCount(1, $res['procesos']);
        $this->assertSame(42, $res['procesos'][0]['posicion']);
        $this->assertSame('Adjudicat', $res['procesos'][0]['estado']);
        $this->assertSame('IES TEST', $res['procesos'][0]['adjudicacion']['centro_nombre']);
    }

    public function test_name_match_is_case_insensitive_and_scoped(): void
    {
        $proceso = $this->proceso();
        $user = User::factory()->create();
        $user->forceFill(['nombre_gva' => 'garcia lopez, ana'])->save();

        ParticipanteProceso::create([
            'proceso_id' => $proceso->id, 'posicion' => 5, 'nombre_gva' => 'GARCIA LOPEZ, ANA',
            'estado' => 'No adjudicat', 'especialidad_codigo' => '590',
        ]);

        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/v1/user/mis-listados')
            ->assertOk()
            ->assertJsonPath('procesos.0.posicion', 5);
    }
}
