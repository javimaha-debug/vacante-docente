<?php

namespace Tests\Feature;

use App\Console\Commands\ImportParticipantesPdf;
use App\Console\Commands\ImportVacantesPdf;
use App\Models\Ccaa;
use App\Models\Colectivo;
use App\Models\GvaNoticia;
use App\Models\ParticipanteProceso;
use App\Models\Proceso;
use App\Models\Specialty;
use App\Models\Vacancy;
use App\Services\GvaAutoImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * Guards the data-integrity bug where a misclassified or unparseable PDF parsed
 * to 0 rows and then deleted every existing record for the proceso.
 */
class ImportSafetyGuardTest extends TestCase
{
    use RefreshDatabase;

    private function proceso(string $body = 'MAESTROS'): Proceso
    {
        $cv = Ccaa::firstOrCreate(['code' => 'CV'], ['name' => 'CV', 'is_active' => true]);
        $col = Colectivo::create(['ccaa_id' => $cv->id, 'code' => 'INTERINO', 'name' => 'Interins', 'body' => $body]);

        return Proceso::create([
            'ccaa_id' => $cv->id, 'colectivo_id' => $col->id, 'anyo' => 2026,
            'curso' => '2026-2027', 'nombre' => "Interins {$body} 2026-2027", 'estado' => 'publicado',
        ]);
    }

    /** Run a command instance against canned PDF text without needing pdftotext. */
    private function runWithText(ImportParticipantesPdf|ImportVacantesPdf $cmd, array $args): int
    {
        $cmd->setLaravel($this->app);

        return $cmd->run(new ArrayInput($args), new BufferedOutput());
    }

    public function test_zero_row_participant_import_does_not_delete_existing_records(): void
    {
        $proceso = $this->proceso();

        // Existing list that must survive a bad import.
        ParticipanteProceso::create([
            'proceso_id' => $proceso->id, 'posicion' => 1, 'nombre_gva' => 'GARCIA, ANA',
            'estado' => 'Activat', 'especialidad_codigo' => '120',
        ]);
        ParticipanteProceso::create([
            'proceso_id' => $proceso->id, 'posicion' => 2, 'nombre_gva' => 'LOPEZ, BEA',
            'estado' => 'Activat', 'especialidad_codigo' => '120',
        ]);

        $tmp = tempnam(sys_get_temp_dir(), 'pdf');

        // A document the participant parser yields 0 rows from (e.g. a "llocs
        // oferits" listing misfed to it).
        $cmd = new class extends ImportParticipantesPdf {
            protected function extractText(string $path): ?string
            {
                return "LLISTAT DEFINITIU DE LLOCS OFERITS\n\nsense cap fila de participant";
            }
        };

        $exit = $this->runWithText($cmd, ['pdf_path' => $tmp, 'proceso_id' => (string) $proceso->id]);

        $this->assertSame(ImportParticipantesPdf::FAILURE, $exit);
        $this->assertSame(2, ParticipanteProceso::where('proceso_id', $proceso->id)->count());

        @unlink($tmp);
    }

    public function test_allow_empty_overrides_the_participant_guard(): void
    {
        $proceso = $this->proceso();
        ParticipanteProceso::create([
            'proceso_id' => $proceso->id, 'posicion' => 1, 'nombre_gva' => 'GARCIA, ANA',
            'estado' => 'Activat', 'especialidad_codigo' => '120',
        ]);

        $tmp = tempnam(sys_get_temp_dir(), 'pdf');
        $cmd = new class extends ImportParticipantesPdf {
            protected function extractText(string $path): ?string
            {
                return "buit";
            }
        };

        $exit = $this->runWithText($cmd, [
            'pdf_path' => $tmp, 'proceso_id' => (string) $proceso->id, '--allow-empty' => true,
        ]);

        $this->assertSame(ImportParticipantesPdf::SUCCESS, $exit);
        $this->assertSame(0, ParticipanteProceso::where('proceso_id', $proceso->id)->count());

        @unlink($tmp);
    }

    public function test_zero_row_vacancy_import_does_not_delete_existing_records(): void
    {
        $cv = Ccaa::firstOrCreate(['code' => 'CV'], ['name' => 'CV', 'is_active' => true]);
        $proceso = $this->proceso('SECUNDARIA');
        $specialty = Specialty::create([
            'code' => '218', 'codigo' => '218', 'name' => 'Orientación', 'body' => 'Profesores de Enseñanza Secundaria',
            'education_level' => 'secundaria', 'cuerpo' => 'SECUNDARIA', 'ccaa_id' => $cv->id, 'is_active' => true,
        ]);
        Vacancy::create([
            'specialty_id' => $specialty->id, 'proceso_id' => $proceso->id, 'ccaa_id' => $cv->id,
            'num' => 1, 'num_orden' => 1, 'provincia' => 'València', 'localidad' => 'VALÈNCIA',
            'centro_codigo' => '46000000', 'centro_nombre' => 'IES TEST', 'lloc' => '900001',
            'tipo_centro' => 'Secundaria', 'is_active' => true, 'year' => 2026,
        ]);

        $tmp = tempnam(sys_get_temp_dir(), 'pdf');
        $cmd = new class extends ImportVacantesPdf {
            protected function extractText(string $path): ?string
            {
                return "ANNEX SENSE FILES\n\nno hi ha cap vacant ací";
            }
        };

        $exit = $this->runWithText($cmd, ['path' => $tmp, 'proceso_id' => (string) $proceso->id]);

        $this->assertSame(ImportVacantesPdf::FAILURE, $exit);
        $this->assertSame(1, Vacancy::where('proceso_id', $proceso->id)->count());

        @unlink($tmp);
    }

    public function test_denylisted_document_types_are_not_auto_classified(): void
    {
        $this->proceso('MAESTROS');
        $service = app(GvaAutoImportService::class);

        // "Llocs oferits" definitive list must not auto-map to a participant/vacancy import.
        $noticia = GvaNoticia::create([
            'titulo' => 'Llistat definitiu de llocs oferits per a Mestres, Secundària i Altres cossos',
            'url' => 'https://ceice.gva.es/docs/260602_pue_def.pdf',
            'tipo' => 'PDF',
        ]);

        $this->assertFalse($service->isImportable($noticia));
        $this->assertNull($service->resolveTarget($noticia));
    }

    public function test_import_into_with_unknown_kind_routes_to_manual_review(): void
    {
        $proceso = $this->proceso('MAESTROS');
        $noticia = GvaNoticia::create([
            'titulo' => 'Barem provisional', // denylisted → unclassifiable
            'url' => 'https://ceice.gva.es/docs/barem_2026.pdf',
            'tipo' => 'PDF',
        ]);

        $resumen = app(GvaAutoImportService::class)->importInto($noticia, 'desconocido', $proceso);

        $this->assertSame('sin_proceso', $noticia->fresh()->import_estado);
        $this->assertStringContainsString('manual', $resumen);
    }
}
