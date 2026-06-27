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
                            {--format=orientacion : PDF layout — "orientacion" (specialty-section list) or "suprimidos" (per-row puesto)}
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

        $format = (string) $this->option('format');
        $cuerpo = $proceso->colectivo?->body;
        $now = Carbon::now();

        $parsed = $format === 'suprimidos' ? $this->parseSuprimidosText($text) : $this->parseText($text);

        if (empty($parsed)) {
            $this->warn('No vacancy rows could be parsed from the PDF.');

            return self::SUCCESS;
        }

        $rows = [];
        $unresolved = [];

        foreach ($parsed as $i => $r) {
            // Orientación rows carry a specialty code; suprimidos rows carry a
            // free-text puesto that we match against specialty names.
            $specialty = $format === 'suprimidos'
                ? $this->resolveSpecialtyByName($r['puesto'], $cuerpo)
                : $this->resolveSpecialty($r['specialty_code'], $cuerpo);

            if (! $specialty) {
                $unresolved[$format === 'suprimidos' ? $r['puesto'] : $r['specialty_code']] = true;

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

    /**
     * Parse the "suprimidos" layout, where each row is a displaced post:
     *   LLOC  DESCRIPCIÓ PUESTO  CENTRE  LOCALITAT  [RL]  [OBSERVACIONS]
     * Columns are separated by 2+ spaces (pdftotext -layout). There is no
     * specialty section header — the puesto description identifies it per row.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parseSuprimidosText(string $text): array
    {
        $rows = [];

        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = rtrim($line);

            // A data row begins with the lloc code (4+ digits).
            if (! preg_match('/^\s*(\d{4,})\s{2,}(.+)$/u', $line, $m)) {
                continue;
            }

            $lloc = $m[1];
            $cols = preg_split('/\s{2,}/', trim($m[2]));

            $puesto = trim($cols[0] ?? '');
            $centro = trim($cols[1] ?? '');
            $localitat = trim($cols[2] ?? '');

            // Optional requisit lingüístic column (SI/SÍ/S/NO/N), then observations.
            $idx = 3;
            $rl = '';
            if (isset($cols[3]) && preg_match('/^(s[íi]?|no?)$/iu', trim($cols[3]))) {
                $rl = trim($cols[3]);
                $idx = 4;
            }
            $obs = trim(implode(' ', array_slice($cols, $idx)));

            if ($puesto === '') {
                continue;
            }

            // A centre code may appear inside the centre/observations text.
            $codi = null;
            if (preg_match('/(\d{8})/', $centro.' '.$obs, $cm)) {
                $codi = $cm[1];
            }

            $reqLing = (bool) preg_match('/^s/iu', $rl) || $this->detectReqLing($obs);

            $rows[] = [
                'lloc' => $lloc,
                'puesto' => $puesto,
                'centro' => $centro,
                'localidad' => $localitat,
                'codi' => $codi,
                'provincia' => $codi ? $this->provinceFromCode($codi) : 'València',
                'tipo_centro' => $this->centerTypeFromName($centro),
                'req_ling' => $reqLing,
                'itinerante' => (bool) preg_match('/itinerant/iu', $obs),
                'observaciones' => $obs !== '' ? $obs : null,
                'num' => null,
            ];
        }

        return $rows;
    }

    /**
     * Resolve a specialty from a free-text puesto description (Valencian or
     * Spanish), preferring the proceso's cuerpo. Exact accent-insensitive match
     * first, then a best fuzzy match above a similarity threshold.
     */
    /**
     * Common Valencian puesto descriptions → GVA specialty code. Starting set;
     * extend against the real suprimidos PDF as needed.
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
