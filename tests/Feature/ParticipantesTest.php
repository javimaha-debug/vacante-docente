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

    public function test_parser_mestres_expands_habilitations(): void
    {
        // Provisional maestros list: situació + habilitation columns. One row
        // is emitted per (person × habilitation), mapped to internal codes.
        $layout = <<<TXT
           1    MARQUET SOLDEVILA, ROSA MARIA                 AMB SERVEIS       INF   PRI
           2    LOPEZ MORA, ANA ISABEL                        SENSE SERVEIS           PRI   PT   AL
        TXT;

        $rows = (new ImportParticipantesPdf())->parseText($layout);

        // 2 (INF, PRI) + 3 (PRI, PT, AL) = 5 rows.
        $this->assertCount(5, $rows);

        $rosa = array_values(array_filter($rows, fn ($r) => str_starts_with($r['nombre_gva'], 'MARQUET')));
        $this->assertSame('AMB SERVEIS', $rosa[0]['estado']);
        $this->assertEqualsCanonicalizing(['120', '121'], array_column($rosa, 'especialidad_codigo'));

        $ana = array_values(array_filter($rows, fn ($r) => str_starts_with($r['nombre_gva'], 'LOPEZ')));
        $this->assertSame('SENSE SERVEIS', $ana[0]['estado']);
        $this->assertEqualsCanonicalizing(['121', '126', '125'], array_column($ana, 'especialidad_codigo'));
        $this->assertSame(2, $ana[0]['posicion']);
    }

    public function test_parser_seccionado_groups_participants_by_specialty(): void
    {
        // Provisional sectioned list (secundaria/FP): "(CODI) NOM" headers, with
        // positions restarting per section.
        $layout = <<<TXT
        (218) ORIENTACIÓ EDUCATIVA                                  Col·lectiu
                        1 SERRANO ALMARCHA, MARIA JESUS              AMB SERVEIS
                        2 GINER ALBIACH, JUAN                        AMB SERVEIS   (*)
        (206) MATEMÀTIQUES
                        1 PEREZ VIDAL, ANGEL MIGUEL                  SENSE SERVEIS
        TXT;

        $rows = (new ImportParticipantesPdf())->parseText($layout);

        $this->assertCount(3, $rows);
        $this->assertSame('218', $rows[0]['especialidad_codigo']);
        $this->assertSame('ORIENTACIÓ EDUCATIVA', $rows[0]['especialidad_nombre']);
        $this->assertSame('SERRANO ALMARCHA, MARIA JESUS', $rows[0]['nombre_gva']);
        $this->assertSame('AMB SERVEIS', $rows[0]['estado']);
        $this->assertSame(2, $rows[1]['posicion']);
        $this->assertSame('206', $rows[2]['especialidad_codigo']);
        $this->assertSame('SENSE SERVEIS', $rows[2]['estado']);
        $this->assertSame(1, $rows[2]['posicion']);
    }

    public function test_cambios_endpoint_reflects_latest_import(): void
    {
        $proceso = $this->makeProceso();

        \App\Models\ParticipanteImportacion::create([
            'proceso_id' => $proceso->id, 'importado_en' => now()->subDay(),
            'total' => 5, 'nuevos' => 0, 'modificados' => 0, 'eliminados' => 0, 'es_primera' => true,
        ]);
        \App\Models\ParticipanteImportacion::create([
            'proceso_id' => $proceso->id, 'importado_en' => now(),
            'total' => 6, 'nuevos' => 2, 'modificados' => 1, 'eliminados' => 0, 'es_primera' => false,
        ]);

        $this->getJson("/api/v1/participantes/{$proceso->id}/cambios")
            ->assertOk()
            ->assertJsonPath('has_changes', true)
            ->assertJsonPath('nuevos', 2)
            ->assertJsonPath('modificados', 1);
    }

    public function test_cambios_endpoint_ignores_first_import(): void
    {
        $proceso = $this->makeProceso();
        \App\Models\ParticipanteImportacion::create([
            'proceso_id' => $proceso->id, 'importado_en' => now(),
            'total' => 5, 'nuevos' => 0, 'modificados' => 0, 'eliminados' => 0, 'es_primera' => true,
        ]);

        $this->getJson("/api/v1/participantes/{$proceso->id}/cambios")
            ->assertOk()
            ->assertJsonPath('has_changes', false);
    }

    public function test_mi_posicion_returns_listado_date_from_latest_import(): void
    {
        $proceso = $this->makeProceso();
        ParticipanteProceso::create(['proceso_id' => $proceso->id, 'posicion' => 7, 'nombre_gva' => 'PEREZ GOMEZ, ANA', 'estado' => 'Activat']);
        \App\Models\ParticipanteImportacion::create([
            'proceso_id' => $proceso->id, 'importado_en' => \Illuminate\Support\Carbon::parse('2026-06-20'),
            'total' => 1, 'nuevos' => 0, 'modificados' => 0, 'eliminados' => 0, 'es_primera' => true,
        ]);

        $user = User::factory()->create(['nombre_gva' => 'PEREZ GOMEZ, ANA']);
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/participantes/{$proceso->id}/mi-posicion")
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('posicion', 7)
            ->assertJsonPath('listado_fecha', '2026-06-20');
    }

    public function test_mi_posicion_prefers_row_matching_user_specialty(): void
    {
        $cv = Ccaa::create(['code' => 'CV', 'name' => 'CV', 'is_active' => true]);
        $col = Colectivo::create(['ccaa_id' => $cv->id, 'code' => 'INTERINO', 'name' => 'Interins', 'body' => 'SECUNDARIA']);
        $proceso = Proceso::create([
            'ccaa_id' => $cv->id, 'colectivo_id' => $col->id, 'anyo' => 2026, 'curso' => '2026-2027',
            'nombre' => 'Interins 2026-2027', 'estado' => 'publicado',
        ]);
        $mates = \App\Models\Specialty::create(['code' => '206', 'codigo' => '206', 'name' => 'Matemàtiques', 'body' => 'PES', 'education_level' => 'secundaria', 'cuerpo' => 'SECUNDARIA', 'ccaa_id' => $cv->id]);

        // Same person appears in two specialty sections.
        ParticipanteProceso::create(['proceso_id' => $proceso->id, 'posicion' => 3, 'nombre_gva' => 'PEREZ GOMEZ, ANA', 'estado' => 'Activat', 'especialidad_codigo' => '218']);
        ParticipanteProceso::create(['proceso_id' => $proceso->id, 'posicion' => 11, 'nombre_gva' => 'PEREZ GOMEZ, ANA', 'estado' => 'Activat', 'especialidad_codigo' => '206']);

        $user = User::factory()->create(['nombre_gva' => 'PEREZ GOMEZ, ANA']);
        \App\Models\UserEspecialidad::create(['user_id' => $user->id, 'specialty_id' => $mates->id, 'anyo' => 2026]);
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/participantes/{$proceso->id}/mi-posicion")
            ->assertOk()
            ->assertJsonPath('posicion', 11)
            ->assertJsonPath('especialidad_codigo', '206');
    }

    public function test_mi_posicion_exposes_cambio_flag(): void
    {
        $proceso = $this->makeProceso();
        ParticipanteProceso::create(['proceso_id' => $proceso->id, 'posicion' => 7, 'nombre_gva' => 'PEREZ GOMEZ, ANA', 'estado' => 'Activat', 'cambio' => 'modificado']);

        $user = User::factory()->create(['nombre_gva' => 'PEREZ GOMEZ, ANA']);
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/participantes/{$proceso->id}/mi-posicion")
            ->assertOk()
            ->assertJsonPath('cambio', 'modificado');
    }

    public function test_parser_inici_de_curs_adjudicacio_format(): void
    {
        // GVA "ADJUDICACIÓ ... INICI DE CURS": "CODI NOM" section headers and a
        // standalone status line per person; adjudications carry a detail block.
        $layout = <<<TXT
        ADJUDICACIÓ DE PERSONAL DOCENT INICI DE CURS 2025/2026

        3A1 CUINA I PASTISSERIA

        1     MASIA VALLES, ELISABET

                                                                Desactivat

        5     VIZCAINO SANCHIS, GEMMA                Petición:   2   Voluntaria

                 898526 SANT VICENT DEL RASPEIG(03010442)CIPFP CANASTELL
                        3A1 / CUINA I PASTISSERIA
               Jornada completa                       VACANT          Adjudicat

        206 MATEMÀTIQUES

        1     PEREZ VIDAL, ANGEL MIGUEL                                Voluntaria

                                                                Activat
        TXT;

        $rows = (new ImportParticipantesPdf())->parseText($layout);

        $this->assertCount(3, $rows);

        $this->assertSame('3A1', $rows[0]['especialidad_codigo']);
        $this->assertSame('MASIA VALLES, ELISABET', $rows[0]['nombre_gva']);
        $this->assertSame('Desactivat', $rows[0]['estado']);

        // The adjudicated row keeps its position, status and adjudication detail.
        $adj = $rows[1];
        $this->assertSame('VIZCAINO SANCHIS, GEMMA', $adj['nombre_gva']);
        $this->assertSame('Adjudicat', $adj['estado']);
        $this->assertSame('898526', $adj['lloc_adjudicado']);
        $this->assertSame('03010442', $adj['centro_codigo']);
        $this->assertStringContainsString('CANASTELL', $adj['centro_nombre']);

        // New section, position restarts, status normalised.
        $this->assertSame('206', $rows[2]['especialidad_codigo']);
        $this->assertSame('Activat', $rows[2]['estado']);
        $this->assertSame(1, $rows[2]['posicion']);
    }

    public function test_parser_captures_centre_code_in_adjudication(): void
    {
        $rows = (new ImportParticipantesPdf())->parseText(self::LAYOUT);

        $this->assertSame('46011223', $rows[2]['centro_codigo']);
    }

    public function test_matching_writes_user_historial_for_adjudicated(): void
    {
        $cv = Ccaa::create(['code' => 'CV', 'name' => 'CV', 'is_active' => true]);
        $col = Colectivo::create(['ccaa_id' => $cv->id, 'code' => 'INTERINO', 'name' => 'Interins', 'body' => 'SECUNDARIA']);
        $spec = \App\Models\Specialty::create(['code' => '218', 'codigo' => '218', 'name' => 'Orientació', 'body' => 'Profesores de Enseñanza Secundaria', 'education_level' => 'secundaria', 'ccaa_id' => $cv->id]);
        $proceso = Proceso::create([
            'ccaa_id' => $cv->id, 'colectivo_id' => $col->id, 'anyo' => 2026, 'curso' => '2026-2027',
            'nombre' => 'Interins 2026-2027', 'estado' => 'publicado',
        ]);
        $centro = \App\Models\Centro::create(['ccaa_id' => $cv->id, 'codigo' => '46011223', 'nombre' => 'IES LA FONT', 'tipo' => 'IES', 'localidad' => 'València', 'provincia' => 'València']);
        $user = User::factory()->create(['nombre_gva' => 'MARTINEZ RUIZ, LAURA']);

        $rows = [[
            'posicion' => 3, 'nombre_gva' => 'MARTINEZ RUIZ, LAURA', 'estado' => 'Adjudicat',
            'lloc_adjudicado' => '896238', 'centro_nombre' => 'IES LA FONT', 'centro_codigo' => '46011223',
            'localitat' => 'València', 'especialidad_codigo' => '218', 'jornada' => 'Jornada completa',
        ]];

        // matchToUsers is private; exercise it directly.
        $cmd = new ImportParticipantesPdf();
        $m = new \ReflectionMethod($cmd, 'matchToUsers');
        $m->setAccessible(true);
        $updates = $m->invoke($cmd, $proceso, $rows);

        $this->assertSame(1, $updates);
        $this->assertDatabaseHas('user_especialidades', [
            'user_id' => $user->id, 'specialty_id' => $spec->id, 'anyo' => 2026, 'posicion_bolsa' => 3, 'estado_bolsa' => 'Adjudicat',
        ]);
        $this->assertDatabaseHas('user_historial', [
            'user_id' => $user->id, 'specialty_id' => $spec->id, 'anyo' => 2026,
            'estado' => 'Adjudicat', 'centro_adjudicado_id' => $centro->id,
            'lloc_adjudicado' => '896238', 'jornada_adjudicada' => 'Jornada completa',
        ]);
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
