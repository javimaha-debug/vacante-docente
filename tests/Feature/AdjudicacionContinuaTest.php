<?php

namespace Tests\Feature;

use App\Console\Commands\ImportAdjudicacionContinua;
use App\Models\AdjudicacionContinua;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdjudicacionContinuaTest extends TestCase
{
    use RefreshDatabase;

    public function test_endpoint_returns_user_weekly_history_newest_first(): void
    {
        $user = User::factory()->create(['nombre_gva' => 'PEREZ GOMEZ, ANA']);

        AdjudicacionContinua::create(['fecha' => '2026-05-27', 'cuerpo' => 'SECUNDARIA', 'nombre_gva' => 'PEREZ GOMEZ, ANA', 'especialidad_codigo' => '218', 'estado' => 'Desactivat', 'posicion' => 12]);
        AdjudicacionContinua::create(['fecha' => '2026-06-02', 'cuerpo' => 'SECUNDARIA', 'nombre_gva' => 'PEREZ GOMEZ, ANA', 'especialidad_codigo' => '218', 'estado' => 'Adjudicat', 'centro_nombre' => 'IES LA FONT', 'lloc_adjudicado' => '900001']);
        AdjudicacionContinua::create(['fecha' => '2026-06-02', 'cuerpo' => 'SECUNDARIA', 'nombre_gva' => 'OTRA PERSONA, JUAN', 'estado' => 'Adjudicat']);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user/adjudicaciones-continuas')
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.fecha', '2026-06-02')
            ->assertJsonPath('data.0.estado', 'Adjudicat')
            ->assertJsonPath('data.0.centro', 'IES LA FONT')
            ->assertJsonPath('data.1.fecha', '2026-05-27');
    }

    public function test_endpoint_requires_nombre_gva(): void
    {
        $user = User::factory()->create(['nombre_gva' => null]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user/adjudicaciones-continuas')
            ->assertOk()
            ->assertJsonPath('found', false)
            ->assertJsonCount(0, 'data');
    }

    public function test_command_resolves_fecha_and_cuerpo(): void
    {
        $cmd = new ImportAdjudicacionContinua();

        $fecha = new \ReflectionMethod($cmd, 'resolveFecha');
        $fecha->setAccessible(true);
        $cuerpo = new \ReflectionMethod($cmd, 'resolveCuerpo');
        $cuerpo->setAccessible(true);
        $curso = new \ReflectionMethod($cmd, 'cursoFromFecha');
        $curso->setAccessible(true);

        // From the title.
        $d = $fecha->invoke($cmd, 'ADJUDICACIÓ DE PERSONAL DOCENT INTERÍ DIA 02/06/2026', 'x.pdf');
        $this->assertSame('2026-06-02', $d->toDateString());

        // From the filename when the title lacks it.
        $d2 = $fecha->invoke($cmd, 'sin fecha', '260602_lis_sec.pdf');
        $this->assertSame('2026-06-02', $d2->toDateString());

        $this->assertSame('SECUNDARIA', $cuerpo->invoke($cmd, '260602_lis_sec.pdf'));
        $this->assertSame('MAESTROS', $cuerpo->invoke($cmd, '260602_lis_mae.pdf'));

        // Course label spans Sept→Aug.
        $this->assertSame('2025-2026', $curso->invoke($cmd, Carbon::parse('2026-06-02')));
        $this->assertSame('2026-2027', $curso->invoke($cmd, Carbon::parse('2026-10-01')));
    }
}
