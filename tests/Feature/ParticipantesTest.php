<?php

namespace Tests\Feature;

use App\Console\Commands\ImportParticipantesPdf;
use App\Models\Ccaa;
use App\Models\Colectivo;
use App\Models\ParticipanteProceso;
use App\Models\Proceso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ParticipantesTest extends TestCase
{
    use RefreshDatabase;

    private const LAYOUT = <<<TXT
    Llistat de participants

    1    PEREZ GOMEZ, ANA            Activat
    2    GARCIA LOPEZ, JUAN          Desactivat
    3    MARTINEZ RUIZ, LAURA        Adjudicat   896238   VALÈNCIA(46011223) IES LA FONT   218 / ORIENTACIÓ EDUCATIVA   Jornada completa
    TXT;

    private function makeProceso(): Proceso
    {
        $cv = Ccaa::create(['code' => 'CV', 'name' => 'CV', 'is_active' => true]);
        $col = Colectivo::create(['ccaa_id' => $cv->id, 'code' => 'INTERINO', 'name' => 'Interins', 'body' => 'SECUNDARIA']);

        return Proceso::create([
            'ccaa_id' => $cv->id, 'colectivo_id' => $col->id, 'anyo' => 2026, 'curso' => '2026-2027',
            'nombre' => 'Interins 2026-2027', 'estado' => 'publicado',
        ]);
    }

    public function test_parser_extracts_positions_status_and_adjudication(): void
    {
        $rows = (new ImportParticipantesPdf())->parseText(self::LAYOUT);

        $this->assertCount(3, $rows);
        $this->assertSame(1, $rows[0]['posicion']);
        $this->assertSame('PEREZ GOMEZ, ANA', $rows[0]['nombre_gva']);
        $this->assertSame('Activat', $rows[0]['estado']);

        $adj = $rows[2];
        $this->assertSame('Adjudicat', $adj['estado']);
        $this->assertSame('896238', $adj['lloc_adjudicado']);
        $this->assertSame('VALÈNCIA', $adj['localitat']);
        $this->assertStringContainsString('IES LA FONT', $adj['centro_nombre']);
        $this->assertSame('218', $adj['especialidad_codigo']);
        $this->assertStringContainsString('Jornada', $adj['jornada']);
    }

    public function test_participantes_endpoint_is_public_paginated_searchable(): void
    {
        $proceso = $this->makeProceso();
        ParticipanteProceso::create(['proceso_id' => $proceso->id, 'posicion' => 1, 'nombre_gva' => 'PEREZ GOMEZ, ANA', 'estado' => 'Activat']);
        ParticipanteProceso::create(['proceso_id' => $proceso->id, 'posicion' => 2, 'nombre_gva' => 'GARCIA LOPEZ, JUAN', 'estado' => 'Desactivat']);

        $this->getJson("/api/v1/participantes/{$proceso->id}")
            ->assertOk()->assertJsonCount(2, 'data');

        $this->getJson("/api/v1/participantes/{$proceso->id}?nombre=perez")
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre_gva', 'PEREZ GOMEZ, ANA');
    }

    public function test_mi_posicion_matches_by_nombre_gva(): void
    {
        $proceso = $this->makeProceso();
        ParticipanteProceso::create(['proceso_id' => $proceso->id, 'posicion' => 7, 'nombre_gva' => 'PEREZ GOMEZ, ANA', 'estado' => 'Activat']);

        $user = User::factory()->create(['nombre_gva' => 'PEREZ GOMEZ, ANA']);
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/participantes/{$proceso->id}/mi-posicion")
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('posicion', 7)
            ->assertJsonPath('estado', 'Activat');
    }

    public function test_mi_posicion_requires_nombre_gva(): void
    {
        $proceso = $this->makeProceso();
        $user = User::factory()->create(['nombre_gva' => null]);
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/participantes/{$proceso->id}/mi-posicion")->assertStatus(422);
    }

    public function test_parser_normalizes_spanish_statuses(): void
    {
        $layout = <<<TXT
        1    PEREZ GOMEZ, ANA            Activado
        2    GARCIA LOPEZ, JUAN          Desactivado
        TXT;

        $rows = (new ImportParticipantesPdf())->parseText($layout);

        $this->assertSame('Activat', $rows[0]['estado']);
        $this->assertSame('Desactivat', $rows[1]['estado']);
    }

    public function test_parser_handles_multiline_adjudication(): void
    {
        $layout = <<<TXT
        3    MARTINEZ RUIZ, LAURA        Adjudicat
             896238
             VALÈNCIA(46011223) IES LA FONT
             218 / ORIENTACIÓ EDUCATIVA
             Jornada completa
        4    SOLER PONS, MARC            Activat
        TXT;

        $rows = (new ImportParticipantesPdf())->parseText($layout);

        $this->assertCount(2, $rows);

        $adj = $rows[0];
        $this->assertSame('Adjudicat', $adj['estado']);
        $this->assertSame('896238', $adj['lloc_adjudicado']);
        $this->assertSame('VALÈNCIA', $adj['localitat']);
        $this->assertStringContainsString('IES LA FONT', $adj['centro_nombre']);
        $this->assertSame('218', $adj['especialidad_codigo']);
        $this->assertStringContainsString('Jornada', $adj['jornada']);

        // The following participant is parsed normally, not swallowed.
        $this->assertSame(4, $rows[1]['posicion']);
        $this->assertSame('Activat', $rows[1]['estado']);
    }
}
