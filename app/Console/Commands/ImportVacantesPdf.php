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

    protected $description = 'Parse a GVA vacancies PDF and import its rows for a proceso.';

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

        $cuerpo = $proceso->colectivo?->body;
        $now = Carbon::now();

        $parsed = $this->parseText($text);

        if (empty($parsed)) {
            $this->warn('No vacancy rows could be parsed from the PDF.');

            return self::SUCCESS;
        }

        $rows = [];
        $unresolved = [];

        foreach ($parsed as $i => $r) {
            // The catalogue is now keyed by the real GVA section codes, so
            // resolve by code first (exact, cuerpo-preferred) and fall back to
            // the specialty NAME for any older/edge layouts.
            $specialty = ($r['specialty_code'] ? $this->resolveSpecialty($r['specialty_code'], $cuerpo) : null)
                ?? $this->resolveSpecialtyByName($r['specialty_name'] ?? '', $cuerpo);

            if (! $specialty) {
                $unresolved[$r['specialty_code'].' '.($r['specialty_name'] ?? '')] = true;

                continue;
            }

            $num = $r['num'] ?? ($i + 1);

            $rows[] = [
                'specialty_id' => $specialty->id,
                'proceso_id' => $proceso->id,
                'ccaa_id' => $proceso->ccaa_id,
                'num' => $num,
                'num_orden' => $num,
                'provincia' => $r['provincia'],
                'localidad' => $r['localidad'],
                'centro_codigo' => $r['codi'] ?? '',
                'codi_centre' => $r['codi'] ?? null,
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
            $this->warn('Unresolved (skipped): '.implode(' | ', array_keys($unresolved)));
        }

        if ($this->option('dry-run')) {
            $this->line('Dry run — nothing was written.');

            return self::SUCCESS;
        }

        // Diff vs the previous listing (keyed by lloc) to flag new/modified
        // vacancies and record an import summary for the "listado actualizado"
        // banner + notifications.
        $old = Vacancy::where('proceso_id', $proceso->id)
            ->get(['lloc', 'centro_codigo', 'centro_nombre', 'localidad', 'requisito_linguistico', 'itinerante', 'observaciones', 'specialty_id'])
            ->keyBy('lloc')
            ->map(fn ($v) => $this->signature((array) $v->getAttributes()))
            ->all();
        $isFirst = empty($old);

        $newKeys = [];
        $nuevas = $modificadas = 0;
        foreach ($rows as &$row) {
            $lloc = $row['lloc'];
            $newKeys[$lloc] = true;
            $sig = $this->signature($row);

            if ($isFirst) {
                $row['cambio'] = null;
            } elseif (! isset($old[$lloc])) {
                $row['cambio'] = 'nueva';
                $nuevas++;
            } elseif ($old[$lloc] !== $sig) {
                $row['cambio'] = 'modificada';
                $modificadas++;
            } else {
                $row['cambio'] = null;
            }
            $row['cambio_en'] = $now;
        }
        unset($row);

        $eliminadas = $isFirst ? 0 : count(array_diff_key($old, $newKeys));

        // Re-runnable: replace this proceso's vacancies, then record the import.
        DB::transaction(function () use ($proceso, $rows, $now, $isFirst, $nuevas, $modificadas, $eliminadas) {
            Vacancy::where('proceso_id', $proceso->id)->delete();

            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('vacancies')->insert($chunk);
            }

            \App\Models\ProcesoImportacion::create([
                'proceso_id' => $proceso->id,
                'importado_en' => $now,
                'total' => count($rows),
                'nuevas' => $nuevas,
                'modificadas' => $modificadas,
                'eliminadas' => $eliminadas,
                'es_primera' => $isFirst,
            ]);
        });

        $this->info('Imported '.count($rows)." vacancies for proceso #{$proceso->id} ({$proceso->nombre}).");
        if (! $isFirst) {
            $this->info("Cambios vs listado anterior: {$nuevas} nuevas, {$modificadas} modificadas, {$eliminadas} eliminadas.");
        }

        return self::SUCCESS;
    }

    /**
     * Stable content signature of a vacancy (for change detection).
     *
     * @param  array<string, mixed>  $v
     */
    private function signature(array $v): string
    {
        return implode('|', [
            $v['centro_codigo'] ?? '',
            $v['centro_nombre'] ?? '',
            $v['localidad'] ?? '',
            (int) ($v['requisito_linguistico'] ?? $v['req_ling'] ?? 0),
            (int) ($v['itinerante'] ?? 0),
            trim((string) ($v['observaciones'] ?? $v['observ'] ?? '')),
            $v['specialty_id'] ?? '',
        ]);
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
        $currentCode = null;
        $currentName = null;

        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = rtrim($line);

            if (trim($line) === '') {
                continue;
            }

            // Specialty header, e.g. "201 - FILOSOFIA / FILOSOFÍA" or
            // "2A1 - INSTAL. ... / INSTAL. ...". No 8-digit code on these lines.
            if (preg_match('/^\s*([0-9][A-Z0-9][0-9])\s*[-–]\s*(.+)$/u', $line, $m)
                && ! preg_match('/\d{8}/', $line)) {
                $currentCode = $m[1];
                // Header is "VALENCIÀ / CASTELLANO"; keep the Spanish part for
                // name-based specialty resolution.
                $parts = array_map('trim', explode('/', $m[2]));
                $currentName = end($parts) ?: $m[2];

                continue;
            }

            // Data row: NUM LLOC LOCALITAT CENTRE CODI(8) [ITIN ü] [OBSERVACIONS].
            if (! preg_match('/^\s*(\d+)\s+(\S+)\s+(.+?)\s+(\d{8})\b\s*(.*)$/u', $line, $m)) {
                continue;
            }

            [, $num, $lloc, $middle, $codi, $rest] = $m;

            // LOCALITAT and CENTRE are layout-separated by 2+ spaces.
            $parts = preg_split('/\s{2,}/', trim($middle));
            if (count($parts) >= 2) {
                $localidad = array_shift($parts);
                $centro = implode(' ', $parts);
            } else {
                $localidad = '';
                $centro = trim($middle);
            }

            // The ITIN column is a "ü" check mark; the remainder is observations.
            $rest = trim($rest);
            $itinerante = mb_strpos($rest, 'ü') !== false || (bool) preg_match('/itinerant/iu', $rest);
            $obs = trim(str_replace('ü', '', $rest));

            $rows[] = [
                'specialty_code' => $currentCode,
                'specialty_name' => $currentName,
                'num' => (int) $num,
                'lloc' => $lloc,
                'localidad' => $localidad,
                'centro' => $centro,
                'codi' => $codi,
                'provincia' => $this->provinceFromCode($codi),
                'tipo_centro' => $this->centerTypeFromName($centro),
                'req_ling' => $this->detectReqLing($obs),
                'itinerante' => $itinerante,
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

        // Secondary / FP / language schools (match anywhere — e.g. "SECCIÓ DE L'IES …").
        if (preg_match('/\b(IES|CIPFP|CIFP|IFP|EOI|CEED|FPA|CFPA|EPA|CEPA|INSTITUT)\b/u', $upper)) {
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

    /**
     * Common Valencian specialty names → GVA specialty code, to bridge the gap
     * between the PDF section codes and our internal codes. Extend as needed.
     */
    private const PUESTO_ALIASES = [
        'orientacio educativa' => '117',
        'matematiques' => '104',
        'angles' => '109',
        'frances' => '108',
        'geografia i historia' => '103',
        'llengua castellana i literatura' => '102',
        'fisica i quimica' => '105',
        'biologia i geologia' => '106',
        'educacio fisica' => '116',
        'musica' => '115',
        'filosofia' => '101',
        'tecnologia' => '114',
        'economia' => '113',
        'informatica' => '126',
    ];

    public function resolveSpecialtyByName(string $puesto, ?string $cuerpo): ?Specialty
    {
        $needle = $this->normalize($puesto);
        if ($needle === '') {
            return null;
        }

        // 1) Known Valencian alias → resolve by its specialty code.
        if (isset(self::PUESTO_ALIASES[$needle])) {
            $byCode = $this->resolveSpecialty(self::PUESTO_ALIASES[$needle], $cuerpo);
            if ($byCode) {
                return $byCode;
            }
        }

        $candidates = Specialty::query()
            ->when($cuerpo, fn ($q) => $q->where('cuerpo', $cuerpo))
            ->get();

        if ($candidates->isEmpty()) {
            $candidates = Specialty::all();
        }

        $best = null;
        $bestScore = 0.0;

        foreach ($candidates as $specialty) {
            $hay = $this->normalize($specialty->name);

            if ($hay === $needle) {
                return $specialty;
            }

            similar_text($needle, $hay, $percent);
            if ($percent > $bestScore) {
                $bestScore = $percent;
                $best = $specialty;
            }
        }

        // Only accept a fuzzy match when it is clearly the same puesto.
        return $bestScore >= 85.0 ? $best : null;
    }

    /**
     * Lower-case, accent-stripped, whitespace-collapsed form for matching.
     */
    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, [
            'à' => 'a', 'á' => 'a', 'ä' => 'a', 'è' => 'e', 'é' => 'e', 'ë' => 'e',
            'í' => 'i', 'ï' => 'i', 'ò' => 'o', 'ó' => 'o', 'ö' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ç' => 'c', 'ñ' => 'n',
        ]);

        return preg_replace('/\s+/', ' ', $value);
    }
}
