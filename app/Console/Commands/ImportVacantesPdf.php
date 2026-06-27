<?php

namespace App\Console\Commands;

use App\Models\Proceso;
use App\Models\Specialty;
use App\Models\Vacancy;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class ImportVacantesPdf extends Command
{
    protected $signature = 'vacantes:import-pdf {path : Path to the GVA vacancies PDF}
                            {proceso_id : Proceso the vacancies belong to}
                            {--dry-run : Parse and report without writing to the database}';

    protected $description = 'Parse a GVA vacancies PDF (2026 format) and import its rows for a proceso.';

    /** GVA centre-code province prefixes. */
    private const PROVINCE_PREFIXES = [
        '03' => 'Alacant',
        '12' => 'Castelló',
        '46' => 'València',
    ];

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        $procesoId = (int) $this->argument('proceso_id');

        if (! is_file($path)) {
            $this->error("PDF not found: {$path}");

            return self::FAILURE;
        }

        $proceso = Proceso::find($procesoId);

        if (! $proceso) {
            $this->error("Proceso #{$procesoId} not found.");

            return self::FAILURE;
        }

        $text = $this->extractText($path);

        if ($text === null) {
            return self::FAILURE;
        }

        $parsed = $this->parseText($text);

        if (empty($parsed)) {
            $this->warn('No vacancy rows could be parsed from the PDF.');

            return self::SUCCESS;
        }

        // Resolve specialty codes to ids, preferring the proceso's cuerpo.
        $cuerpo = $proceso->colectivo?->body;
        $rows = [];
        $now = Carbon::now();
        $unresolved = [];

        foreach ($parsed as $r) {
            $specialty = $this->resolveSpecialty($r['specialty_code'], $cuerpo);

            if (! $specialty) {
                $unresolved[$r['specialty_code']] = true;

                continue;
            }

            $rows[] = [
                'specialty_id' => $specialty->id,
                'proceso_id' => $proceso->id,
                'ccaa_id' => $proceso->ccaa_id,
                'num' => $r['num'],
                'num_orden' => $r['num'],
                'provincia' => $r['provincia'],
                'localidad' => $r['localidad'],
                'centro_codigo' => $r['codi'],
                'codi_centre' => $r['codi'],
                'centro_nombre' => $r['centro'],
                'tipo_centro' => $r['tipo_centro'],
                'lloc' => $r['lloc'],
                'req_ling' => $r['req_ling'],
                'requisito_linguistico' => $r['req_ling'],
                'itinerante' => $r['itinerante'],
                'observ' => $r['observaciones'],
                'observaciones' => $r['observaciones'],
                'observ_tags' => json_encode([], JSON_UNESCAPED_UNICODE),
                'is_active' => true,
                'year' => $proceso->anyo,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->info('Parsed '.count($parsed).' rows; '.count($rows).' resolved to a known specialty.');

        if (! empty($unresolved)) {
            $this->warn('Unresolved specialty codes (skipped): '.implode(', ', array_keys($unresolved)));
        }

        if ($this->option('dry-run')) {
            $this->line('Dry run — nothing was written.');

            return self::SUCCESS;
        }

        // Re-runnable: replace this proceso's vacancies.
        DB::transaction(function () use ($proceso, $rows) {
            Vacancy::where('proceso_id', $proceso->id)->delete();

            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('vacancies')->insert($chunk);
            }
        });

        $this->info('Imported '.count($rows)." vacancies for proceso #{$proceso->id} ({$proceso->nombre}).");

        return self::SUCCESS;
    }

    /**
     * Run pdftotext over the PDF and return its plain-text layout.
     */
    private function extractText(string $path): ?string
    {
        $process = new Process(['pdftotext', '-layout', '-enc', 'UTF-8', $path, '-']);
        $process->setTimeout(120);

        try {
            $process->run();
        } catch (\Throwable $e) {
            $this->error('Could not execute pdftotext. Is poppler-utils installed? '.$e->getMessage());

            return null;
        }

        if (! $process->isSuccessful()) {
            $this->error('pdftotext failed: '.trim($process->getErrorOutput()));

            return null;
        }

        return $process->getOutput();
    }

    /**
     * Parse pdftotext "-layout" output into structured vacancy rows.
     *
     * Specialty sections are introduced by a header line such as
     *   "218 - ORIENTACIÓ EDUCATIVA / ORIENTACIÓN EDUCATIVA"
     * Each data row follows the column order:
     *   NUM  LLOC  LOCALITAT  CENTRE  CODI  [OBSERVACIONS]
     *
     * @return array<int, array<string, mixed>>
     */
    public function parseText(string $text): array
    {
        $rows = [];
        $currentSpecialty = null;

        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = rtrim($line);

            if (trim($line) === '') {
                continue;
            }

            // Specialty header, e.g. "218 - ORIENTACIÓ EDUCATIVA / ...".
            if (preg_match('/^\s*([0-9]{3}|[0-9][A-Z][0-9]|[A-Z0-9]{2,4})\s*[-–]\s*(.+)$/u', $line, $m)
                && ! preg_match('/\d{8}/', $line)) {
                $currentSpecialty = $m[1];

                continue;
            }

            // Data row: starts with NUM, contains an 8-digit centre code (CODI).
            if (! preg_match('/^\s*(\d+)\s+(\S+)\s+(.+?)\s+(\d{8})\b\s*(.*)$/u', $line, $m)) {
                continue;
            }

            [, $num, $lloc, $middle, $codi, $obs] = $m;

            // LOCALITAT and CENTRE are layout-separated by 2+ spaces.
            $parts = preg_split('/\s{2,}/', trim($middle));
            if (count($parts) >= 2) {
                $localidad = array_shift($parts);
                $centro = implode(' ', $parts);
            } else {
                $localidad = '';
                $centro = trim($middle);
            }

            $obs = trim($obs);

            $rows[] = [
                'specialty_code' => $currentSpecialty,
                'num' => (int) $num,
                'lloc' => $lloc,
                'localidad' => $localidad,
                'centro' => $centro,
                'codi' => $codi,
                'provincia' => $this->provinceFromCode($codi),
                'tipo_centro' => $this->centerTypeFromName($centro),
                'req_ling' => $this->detectReqLing($obs),
                'itinerante' => (bool) preg_match('/itinerant/iu', $obs),
                'observaciones' => $obs !== '' ? $obs : null,
            ];
        }

        // Rows before any specialty header cannot be attributed; drop them.
        return array_values(array_filter($rows, fn ($r) => $r['specialty_code'] !== null));
    }

    private function provinceFromCode(string $codi): string
    {
        return self::PROVINCE_PREFIXES[substr($codi, 0, 2)] ?? 'València';
    }

    private function centerTypeFromName(string $nombre): string
    {
        $upper = mb_strtoupper($nombre);

        // Secondary / FP / language schools.
        if (preg_match('/^(IES|CIPFP|CIFP|IFP|EOI|CEED|FPA|CFPA|EPA|CEPA)\b/u', $upper)) {
            return 'Secundaria';
        }

        // Infant / primary schools.
        if (preg_match('/^(CEIP|CEE|CRA|EI|EEI|CPEE|CP|COL)\b/u', $upper)) {
            return 'Primaria/Infantil';
        }

        return 'Otro';
    }

    private function detectReqLing(string $obs): bool
    {
        return (bool) preg_match('/(lingüístic|linguistic|requisit\s+ling|valenci[àa])/iu', $obs);
    }

    private function resolveSpecialty(string $code, ?string $cuerpo): ?Specialty
    {
        $query = Specialty::query()->where(function ($q) use ($code) {
            $q->where('codigo', $code)->orWhere('code', $code);
        });

        if ($cuerpo) {
            // Prefer a specialty matching the proceso's cuerpo, fall back to any.
            $preferred = (clone $query)->where('cuerpo', $cuerpo)->first();

            if ($preferred) {
                return $preferred;
            }
        }

        return $query->first();
    }
}
