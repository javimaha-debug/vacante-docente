<?php

namespace Tests\Feature;

use App\Console\Commands\ImportVacantesPdf;
use App\Models\Ccaa;
use App\Models\Colectivo;
use App\Models\Proceso;
use App\Models\Vacancy;
use Database\Seeders\CcaaSeeder;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportSuprimidosParserTest extends TestCase
{
    use RefreshDatabase;

    // Simulated pdftotext -layout output for the suprimidos Secundària format:
    // LLOC  DESCRIPCIÓ PUESTO  CENTRE  LOCALITAT  [RL]  [OBSERVACIONS]
    private const LAYOUT = <<<TXT
    Vacants per supressió i desplaçament — Secundària 2026-2027

    LLOC    LLOC DESCRIPCIÓ            CENTRE                       LOCALITAT      RL    OBSERVACIONS
    896238  ORIENTACIÓ EDUCATIVA       IES LA FONT (46011223)       VALÈNCIA       SI    Centre singular
    896239  MATEMÀTIQUES               IES EL PALMERAL (03067890)   ORIOLA               Lloc difícil provisió
    896240  ANGLÉS                     IES PENYAGOLOSA              CASTELLÓ       NO
    TXT;

    public function test_parser_extracts_suprimidos_columns(): void
    {
        $rows = (new ImportVacantesPdf())->parseSuprimidosText(self::LAYOUT);

        $this->assertCount(3, $rows);

        $first = $rows[0];
        $this->assertSame('896238', $first['lloc']);
        $this->assertSame('ORIENTACIÓ EDUCATIVA', $first['puesto']);
        $this->assertStringContainsString('IES LA FONT', $first['centro']);
        $this->assertSame('VALÈNCIA', $first['localidad']);
        $this->assertSame('46011223', $first['codi']);
        $this->assertSame('València', $first['provincia']);
        $this->assertSame('Secundaria', $first['tipo_centro']);
        $this->assertTrue($first['req_ling']);
        $this->assertSame('Centre singular', $first['observaciones']);

        // Province derived from the embedded centre code (03… → Alacant).
        $this->assertSame('Alacant', $rows[1]['provincia']);
        $this->assertFalse($rows[1]['req_ling']);
        $this->assertSame('Lloc difícil provisió', $rows[1]['observaciones']);

        // No code → default province; explicit RL "NO".
        $this->assertNull($rows[2]['codi']);
        $this->assertSame('València', $rows[2]['provincia']);
        $this->assertFalse($rows[2]['req_ling']);
    }

    public function test_resolve_specialty_by_name_handles_valencian_and_exact(): void
    {
        $this->seed(CcaaSeeder::class);
        $this->seed(SpecialtySeeder::class);

        $cmd = new ImportVacantesPdf();

        // Valencian alias → Spanish specialty.
        $this->assertSame('Orientación Educativa', $cmd->resolveSpecialtyByName('ORIENTACIÓ EDUCATIVA', 'secundaria')?->name);
        $this->assertSame('Matemáticas', $cmd->resolveSpecialtyByName('Matemàtiques', 'secundaria')?->name);
        $this->assertSame('Inglés', $cmd->resolveSpecialtyByName('Anglés', 'secundaria')?->name);
        // Exact Spanish name.
        $this->assertSame('Inglés', $cmd->resolveSpecialtyByName('Inglés', 'secundaria')?->name);
        // Unknown → null.
        $this->assertNull($cmd->resolveSpecialtyByName('Submarinismo Avanzado', 'secundaria'));
    }

    public function test_full_suprimidos_import_writes_vacancies(): void
    {
        $this->seed(CcaaSeeder::class);
        $this->seed(SpecialtySeeder::class);

        $cv = Ccaa::where('code', 'CV')->first();
        $col = Colectivo::create(['ccaa_id' => $cv->id, 'code' => 'SUPRIMIDO', 'name' => 'Suprimits', 'body' => 'secundaria']);
        $proceso = Proceso::create([
            'ccaa_id' => $cv->id, 'colectivo_id' => $col->id, 'anyo' => 2026, 'curso' => '2026-2027',
            'nombre' => 'Suprimits Secundària 2026-2027', 'estado' => 'publicado',
        ]);

        // Mirror the command's resolve+insert path (pdftotext not needed here).
        $cmd = new ImportVacantesPdf();
        $parsed = $cmd->parseSuprimidosText(self::LAYOUT);

        foreach ($parsed as $i => $r) {
            $specialty = $cmd->resolveSpecialtyByName($r['puesto'], 'secundaria');
            $this->assertNotNull($specialty, "Puesto no resuelto: {$r['puesto']}");
            Vacancy::create([
                'specialty_id' => $specialty->id, 'proceso_id' => $proceso->id, 'ccaa_id' => $cv->id,
                'num' => $i + 1, 'num_orden' => $i + 1,
                'provincia' => $r['provincia'], 'localidad' => $r['localidad'],
                'centro_codigo' => $r['codi'] ?? '', 'codi_centre' => $r['codi'],
                'centro_nombre' => $r['centro'], 'tipo_centro' => $r['tipo_centro'], 'lloc' => $r['lloc'],
                'req_ling' => $r['req_ling'], 'requisito_linguistico' => $r['req_ling'],
                'itinerante' => $r['itinerante'], 'observ' => $r['observaciones'], 'observaciones' => $r['observaciones'],
                'is_active' => true, 'year' => 2026,
            ]);
        }

        $this->assertSame(3, Vacancy::where('proceso_id', $proceso->id)->count());
        $this->assertSame(1, Vacancy::where('proceso_id', $proceso->id)->where('requisito_linguistico', true)->count());
        $this->assertSame(1, Vacancy::where('proceso_id', $proceso->id)->where('provincia', 'Alacant')->count());
    }
}
