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

    public function test_requires_nombre_gva_when_not_searching(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/user/mis-listados')
            ->assertOk()
            ->assertJsonPath('configured', false)
            ->assertJsonPath('is_search', false);
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
        // Someone else — must not match an exact own-name lookup.
        ParticipanteProceso::create([
            'proceso_id' => $proceso->id, 'posicion' => 7, 'nombre_gva' => 'OTRO NOMBRE, X',
            'estado' => 'No adjudicat', 'especialidad_codigo' => '590',
        ]);

        Sanctum::actingAs($user->fresh());

        $res = $this->getJson('/api/v1/user/mis-listados')
            ->assertOk()
            ->assertJsonPath('configured', true)
            ->assertJsonPath('is_search', false)
            ->assertJsonPath('nombre_gva', 'GARCIA LOPEZ, ANA')
            ->json();

        $this->assertCount(1, $res['resultados']);
        $this->assertSame('GARCIA LOPEZ, ANA', $res['resultados'][0]['nombre_gva']);
        $this->assertCount(1, $res['resultados'][0]['procesos']);
        $this->assertSame(42, $res['resultados'][0]['procesos'][0]['posicion']);
        $this->assertSame('Adjudicat', $res['resultados'][0]['procesos'][0]['estado']);
        $this->assertSame('IES TEST', $res['resultados'][0]['procesos'][0]['adjudicacion']['centro_nombre']);
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
            ->assertJsonPath('resultados.0.procesos.0.posicion', 5);
    }

    public function test_free_search_finds_other_people_by_partial_name(): void
    {
        $proceso = $this->proceso();
        // The logged-in user has no own listing.
        $user = User::factory()->create();
        $user->forceFill(['nombre_gva' => 'YO MISMO, NADIE'])->save();

        ParticipanteProceso::create([
            'proceso_id' => $proceso->id, 'posicion' => 3, 'nombre_gva' => 'GARCIA LOPEZ, ANA',
            'estado' => 'Activat', 'especialidad_codigo' => '590',
        ]);
        ParticipanteProceso::create([
            'proceso_id' => $proceso->id, 'posicion' => 9, 'nombre_gva' => 'GARCIA PEREZ, LUIS',
            'estado' => 'Activat', 'especialidad_codigo' => '590',
        ]);
        ParticipanteProceso::create([
            'proceso_id' => $proceso->id, 'posicion' => 12, 'nombre_gva' => 'MARTINEZ, EVA',
            'estado' => 'Activat', 'especialidad_codigo' => '590',
        ]);

        Sanctum::actingAs($user->fresh());

        $res = $this->getJson('/api/v1/user/mis-listados?q=garcia')
            ->assertOk()
            ->assertJsonPath('is_search', true)
            ->json();

        // Two GARCIA people, not the MARTINEZ one.
        $names = collect($res['resultados'])->pluck('nombre_gva')->all();
        $this->assertCount(2, $names);
        $this->assertContains('GARCIA LOPEZ, ANA', $names);
        $this->assertContains('GARCIA PEREZ, LUIS', $names);
        $this->assertNotContains('MARTINEZ, EVA', $names);
    }

    public function test_search_matches_accented_names_regardless_of_accents(): void
    {
        $proceso = $this->proceso();
        Sanctum::actingAs(User::factory()->create());

        ParticipanteProceso::create([
            'proceso_id' => $proceso->id, 'posicion' => 8, 'nombre_gva' => 'PÉREZ GARCÍA, JOSÉ',
            'estado' => 'Activat', 'especialidad_codigo' => '590',
        ]);

        // Typed without accents — must still find the accented record.
        $this->getJson('/api/v1/user/mis-listados?q=perez')
            ->assertOk()
            ->assertJsonPath('resultados.0.nombre_gva', 'PÉREZ GARCÍA, JOSÉ');

        // Typed with accents — must also find it.
        $this->getJson('/api/v1/user/mis-listados?q='.urlencode('pérez'))
            ->assertOk()
            ->assertJsonPath('resultados.0.nombre_gva', 'PÉREZ GARCÍA, JOSÉ');
    }

    public function test_user_with_accented_name_finds_themselves(): void
    {
        $proceso = $this->proceso();
        $user = User::factory()->create();
        $user->forceFill(['nombre_gva' => 'PÉREZ GARCÍA, JOSÉ'])->save();

        ParticipanteProceso::create([
            'proceso_id' => $proceso->id, 'posicion' => 8, 'nombre_gva' => 'PÉREZ GARCÍA, JOSÉ',
            'estado' => 'Activat', 'especialidad_codigo' => '590',
        ]);

        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/v1/user/mis-listados')
            ->assertOk()
            ->assertJsonPath('is_search', false)
            ->assertJsonPath('resultados.0.procesos.0.posicion', 8);
    }

    public function test_search_works_without_own_name_configured(): void
    {
        $proceso = $this->proceso();
        Sanctum::actingAs(User::factory()->create()); // no nombre_gva

        ParticipanteProceso::create([
            'proceso_id' => $proceso->id, 'posicion' => 1, 'nombre_gva' => 'SANCHIS, GEMMA',
            'estado' => 'Activat', 'especialidad_codigo' => '590',
        ]);

        $this->getJson('/api/v1/user/mis-listados?q=sanchis')
            ->assertOk()
            ->assertJsonPath('configured', false)
            ->assertJsonPath('is_search', true)
            ->assertJsonPath('resultados.0.nombre_gva', 'SANCHIS, GEMMA');
    }
}
