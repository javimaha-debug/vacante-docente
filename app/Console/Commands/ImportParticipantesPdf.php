<?php

namespace App\Console\Commands;

use App\Models\Centro;
use App\Models\ParticipanteProceso;
use App\Models\Proceso;
use App\Models\Specialty;
use App\Models\User;
use App\Models\UserEspecialidad;
use App\Models\UserHistorial;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class ImportParticipantesPdf extends Command
{
    protected $signature = 'participantes:import-pdf {pdf_path} {proceso_id} {--dry-run} {--allow-empty}';

    protected $description = 'Parse a GVA participant-list PDF and import positions/status for a proceso.';

    /**
     * Maestros habilitation abbreviations → internal specialty codes. The
     * provisional maestros list shows each person's habilitations as columns
     * (INF PRI ING FRA EF MUS PT AL); FRA has no own specialty in our catalogue
     * so it is kept verbatim (and simply won't resolve to a user specialty).
     */
    private const MESTRE_HABILITACIONS = [
        'INF' => ['120', 'Educació Infantil'],
        'PRI' => ['121', 'Educació Primària'],
        'ING' => ['122', 'Anglés'],
        'EF' => ['123', 'Educació Física'],
        'MUS' => ['124', 'Música'],
        'AL' => ['125', 'Audició i Llenguatge'],
        'PT' => ['126', 'Pedagogia Terapèutica'],
        'FRA' => ['127', 'Francés'],
    ];

    /** Specialty bodies that belong to each proceso cuerpo (collisions are code-scoped by this). */
    private const CUERPO_BODIES = [
        'MAESTROS' => ['Maestros'],
        // The "secundaria" interim listing spans every non-maestros cuerpo.
        'SECUNDARIA' => [
            'Profesores de Enseñanza Secundaria',
            'Profesorado Especialista en Sectores Singulares de FP',
            'Profesores de Escuelas Oficiales de Idiomas',
            'Profesores de Música y Artes Escénicas',
            'Profesores de Artes Plásticas y Diseño',
        ],
    ];

    /** Situació tokens used in the provisional lists. */
    private const SITUACIO = 'AMB SERVEIS|SENSE SERVEIS|BAIXA';

    /** Status tokens accepted in either Valencian or Spanish (legacy adjudication list). */
    private const STATUS = 'Activat|Desactivat|Adjudicat|Activado|Desactivado|Adjudicado';

    public function handle(): int
    {
        $path = (string) $this->argument('pdf_path');

        // Allow importing straight from a GVA URL (downloads it first).
        if (preg_match('#^https?://#i', $path)) {
            $path = $this->downloadPdf($path);
            if ($path === null) {
                return self::FAILURE;
            }
        }

        $proceso = Proceso::find((int) $this->argument('proceso_id'));

        if (! is_file($path)) {
            $this->error("PDF no encontrado: {$path}");

            return self::FAILURE;
        }
        if (! $proceso) {
            $this->error('Proceso no encontrado.');

            return self::FAILURE;
        }

        $text = $this->extractText($path);
        if ($text === null) {
            return self::FAILURE;
        }

        $rows = $this->parseText($text);
        $personas = collect($rows)->pluck('nombre_gva')->unique()->count();
        $this->info('Filas (participante × especialidad): '.count($rows)." — personas distintas: {$personas}");

        $this->renderEspecialidadCounts($rows);

        if ($this->option('dry-run')) {
            $this->line('Dry run — no se escribe nada.');

            return self::SUCCESS;
        }

        // Safety guard: a PDF that parses to 0 rows is almost always a parse
        // failure or a misclassified document (e.g. a "llocs oferits" listing fed
        // to the participant parser). Importing it as-is would DELETE every
        // existing participant for this proceso and insert nothing. Abort instead,
        // unless the caller explicitly opted in with --allow-empty.
        if (count($rows) === 0 && ! $this->option('allow-empty')) {
            $existing = ParticipanteProceso::where('proceso_id', $proceso->id)->count();
            $this->error(
                "El PDF no produjo ninguna fila de participantes. Importación abortada para no borrar "
                ."los {$existing} registros existentes del proceso «{$proceso->nombre}». "
                .'Si el listado realmente está vacío, reejecuta con --allow-empty.'
            );

            return self::FAILURE;
        }

        // Drop exact duplicate (persona × especialidad) rows that a malformed
        // PDF could yield — keeps counts and the diff consistent.
        $rows = collect($rows)->unique(fn ($r) => $this->diffKey($r))->values()->all();

        // Diff vs the previous participant listing (keyed by person+especialidad)
        // to flag new/modified entries and record an import summary.
        $old = ParticipanteProceso::where('proceso_id', $proceso->id)
            ->get(['nombre_gva', 'especialidad_codigo', 'posicion', 'estado', 'lloc_adjudicado'])
            ->keyBy(fn ($p) => $this->diffKey((array) $p->getAttributes()))
            ->map(fn ($p) => $this->signature((array) $p->getAttributes()))
            ->all();
        $isFirst = empty($old);

        $now = now();
        $newKeys = [];
        $nuevos = $modificados = 0;
        foreach ($rows as &$row) {
            $key = $this->diffKey($row);
            $newKeys[$key] = true;
            $sig = $this->signature($row);

            if ($isFirst) {
                $row['cambio'] = null;
            } elseif (! isset($old[$key])) {
                $row['cambio'] = 'nuevo';
                $nuevos++;
            } elseif ($old[$key] !== $sig) {
                $row['cambio'] = 'modificado';
                $modificados++;
            } else {
                $row['cambio'] = null;
            }
            $row['cambio_en'] = $now;
        }
        unset($row);

        $removedKeys = $isFirst ? [] : array_keys(array_diff_key($old, $newKeys));
        $eliminados = count($removedKeys);
        // diffKey = "lower(nombre)|especialidad_codigo" → recover the names.
        $removedNames = array_map(fn ($k) => substr($k, 0, strrpos($k, '|') ?: 0) ?: $k, $removedKeys);

        DB::transaction(function () use ($proceso, $rows, $now, $isFirst, $nuevos, $modificados, $eliminados) {
            ParticipanteProceso::where('proceso_id', $proceso->id)->delete();
            foreach (array_chunk($rows, 500) as $chunk) {
                ParticipanteProceso::insert(array_map(fn ($r) => array_merge(
                    array_intersect_key($r, array_flip(self::DB_COLUMNS)),
                    [
                        'proceso_id' => $proceso->id,
                        'cambio' => $r['cambio'] ?? null,
                        'cambio_en' => $r['cambio_en'] ?? null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                ), $chunk));
            }

            \App\Models\ParticipanteImportacion::create([
                'proceso_id' => $proceso->id,
                'importado_en' => $now,
                'total' => count($rows),
                'nuevos' => $nuevos,
                'modificados' => $modificados,
                'eliminados' => $eliminados,
                'es_primera' => $isFirst,
            ]);
        });

        $matched = $this->matchToUsers($proceso, $rows);
        $this->info('Importadas '.count($rows)." filas; {$matched} actualizaciones en perfiles de usuario.");
        if (! $isFirst) {
            $this->info("Cambios vs listado anterior: {$nuevos} nuevos, {$modificados} modificados, {$eliminados} eliminados.");

            $service = app(\App\Services\ListadoNotificacionService::class);
            $notified = $service->notifyParticipantes($proceso, [
                'nuevos' => $nuevos, 'modificados' => $modificados, 'eliminados' => $eliminados,
            ]);
            $notified += $service->notifyEliminados($proceso, $removedNames);
            if ($notified > 0) {
                $this->info("Notificados {$notified} usuarios afectados.");
            }
        }

        return self::SUCCESS;
    }

    /**
     * Download a remote PDF to the local disk and return its absolute path.
     */
    private function downloadPdf(string $url): ?string
    {
        $this->info("Descargando {$url} …");
        try {
            $response = Http::timeout(120)->get($url);
        } catch (\Throwable $e) {
            $this->error('No se pudo descargar el PDF: '.$e->getMessage());

            return null;
        }
        if (! $response->successful()) {
            $this->error('La descarga devolvió HTTP '.$response->status());

            return null;
        }

        $name = basename(parse_url($url, PHP_URL_PATH) ?: 'listado.pdf');
        if (! str_ends_with(mb_strtolower($name), '.pdf')) {
            $name .= '.pdf';
        }
        $relative = 'pdfs/gva/auto/'.$name;
        Storage::disk('local')->put($relative, $response->body());

        return Storage::disk('local')->path($relative);
    }

    protected function extractText(string $path): ?string
    {
        $process = new Process(['pdftotext', '-layout', '-enc', 'UTF-8', $path, '-']);
        $process->setTimeout(120);

        try {
            $process->run();
        } catch (\Throwable $e) {
            $this->error('No se pudo ejecutar pdftotext (¿poppler-utils instalado?): '.$e->getMessage());

            return null;
        }

        if (! $process->isSuccessful()) {
            $this->error('pdftotext falló: '.trim($process->getErrorOutput()));

            return null;
        }

        return $process->getOutput();
    }

    /**
     * Print a per-specialty breakdown of the parsed rows (used both for the
     * real import and for dry-run verification).
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function renderEspecialidadCounts(array $rows): void
    {
        $grouped = collect($rows)->groupBy(fn ($r) => $r['especialidad_codigo'] ?? '—');

        if ($grouped->isEmpty()) {
            return;
        }

        $table = $grouped
            ->map(fn ($group, $code) => [
                'code' => $code,
                'name' => $group->first()['especialidad_nombre'] ?? '',
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->map(fn ($r) => [$r['code'], $r['name'], $r['count']])
            ->values()
            ->all();

        $this->table(['Especialitat', 'Nom', 'Participants'], $table);
        $this->line('Total especialitats: '.count($table));
    }

    /**
     * Dispatch to the right parser by detecting the document layout:
     *  - Provisional maestros list (situació + habilitation columns).
     *  - Provisional sectioned list (secundaria/FP: "(CODI) NOM" sections).
     *  - Legacy adjudication list (Activat/Desactivat/Adjudicat).
     *
     * @return array<int, array<string, mixed>>
     */
    public function parseText(string $text): array
    {
        $isProvisional = (bool) preg_match('/\b(AMB|SENSE) SERVEIS\b/u', $text);

        if ($isProvisional) {
            // Sectioned layout has "(CODI) NOM" specialty headers; the maestros
            // list instead carries habilitation columns on each row.
            return preg_match('/^\s*\([0-9A-Z]{2,4}\)\s+\p{Lu}/mu', $text)
                ? $this->parseSeccionado($text)
                : $this->parseMestres($text);
        }

        // Start-of-course adjudication listing: "CODI NOM" section headers and a
        // standalone Activat/Desactivat line per person (the legacy list instead
        // carries the status inline on the same row).
        if (preg_match('/^[ \t]*(Activat|Desactivat)[ \t]*$/mu', $text)) {
            return $this->parseAdjudicacioInici($text);
        }

        return $this->parseAdjudicacions($text);
    }

    /**
     * Start-of-course adjudication listing (GVA "ADJUDICACIÓ ... INICI DE CURS"):
     *
     *   3A1 CUINA I PASTISSERIA                         <- specialty section
     *   5  VIZCAINO SANCHIS, GEMMA   Petición: 2 ...
     *        898526 SANT VICENT(03010442)CIPFP CANASTELL <- adjudication detail
     *        3A1 / CUINA I PASTISSERIA
     *      Jornada completa        VACANT       Adjudicat
     *   6  LAFUENTE BASTIDA, MARIA ELENA
     *                                           Desactivat
     *
     * @return array<int, array<string, mixed>>
     */
    public function parseAdjudicacioInici(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $rows = [];
        $code = null;
        $name = null;
        $current = null;

        $flush = function () use (&$current, &$rows) {
            if ($current !== null) {
                $rows[] = $current;
                $current = null;
            }
        };

        foreach ($lines as $line) {
            $t = trim($line);
            if ($t === '') {
                continue;
            }

            // Participant row: position + "APELLIDOS, NOM" (comma present).
            if (preg_match('/^(\d+)\s+(\p{Lu}.+?,\s*\p{Lu}.+?)(\s{2,}.*)?$/u', $line, $m)) {
                $flush();
                // Drop trailing annotations the layout appends to the name
                // ("( Esp: 334 )", "Petició:", "Voluntària", "Forçosa").
                $nombre = preg_split('/\s{2,}|\s+\(|\s+Petici|\s+Voluntè/u', $m[2].' ')[0];
                $nombre = preg_replace('/\s+(Voluntari|Voluntàri|For[çc]os).*/iu', '', $nombre);
                $current = $this->emptyRow((int) $m[1], $nombre, '');
                $current['especialidad_codigo'] = $code;
                $current['especialidad_nombre'] = $name;

                continue;
            }

            // Specialty section header: "CODI NOM" (no comma, code 2-4 chars).
            if (! str_contains($t, ',') && preg_match('/^([0-9][0-9A-Z]{1,3})\s+(\p{Lu}[\p{L}0-9·\'.\/\- ]+)$/u', $t, $m)
                && ! preg_match('/\b(Activat|Desactivat|Adjudicat)\b/u', $t)) {
                $flush();
                $code = $m[1];
                $name = trim($m[2]);

                continue;
            }

            if ($current === null) {
                continue;
            }

            // Adjudication detail: LLOC LOCALITAT(CODI) CENTRE.
            if (preg_match('/^\s*(\d{4,8})\s+(.+?)\((\d{4,8})\)\s*(.*)$/u', $line, $m)) {
                $current['lloc_adjudicado'] = trim($m[1]);
                $current['localitat'] = trim($m[2]);
                $current['centro_codigo'] = trim($m[3]);
                $current['centro_nombre'] = trim($m[4]) !== '' ? trim($m[4]) : null;

                continue;
            }

            // Status line (standalone or with jornada/VACANT around it).
            if (preg_match('/\b(Activat|Desactivat|Adjudicat|Activado|Desactivado|Adjudicado)\b/u', $line, $m)) {
                $current['estado'] = $this->normalizeEstado($m[1]);
                if (preg_match('/jornada[^A-Z]*/iu', $line, $jm)) {
                    $current['jornada'] = trim($jm[0]);
                }
            }
        }

        $flush();

        return array_values(array_filter($rows, fn ($r) => $r['especialidad_codigo'] !== null));
    }

    private function emptyRow(int $posicion, string $nombre, string $estado): array
    {
        return [
            'posicion' => $posicion,
            'nombre_gva' => $this->normalizeName($nombre),
            'estado' => $estado,
            'lloc_adjudicado' => null,
            'centro_nombre' => null,
            'localitat' => null,
            'especialidad_codigo' => null,
            'especialidad_nombre' => null, // display only — stripped before insert
            'jornada' => null,
        ];
    }

    /** Columns that actually exist on participantes_proceso. */
    private const DB_COLUMNS = [
        'posicion', 'nombre_gva', 'estado', 'lloc_adjudicado',
        'centro_nombre', 'localitat', 'especialidad_codigo', 'jornada',
    ];

    /**
     * Provisional maestros list: one ordered bolsa where each person carries
     * the specialties they are habilitated for as trailing column tokens.
     * Emits one row per (person × habilitation).
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseMestres(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $n = count($lines);
        $rows = [];
        $pending = null; // name carried over when it wrapped to its own line

        for ($i = 0; $i < $n; $i++) {
            $line = rtrim($lines[$i]);
            if (trim($line) === '') {
                continue;
            }

            // Full row: posició + nom + situació + habilitacions.
            if (preg_match('/^\s*(\d+)\s+(.+?,\s*.+?)\s{2,}('.self::SITUACIO.')\b(.*)$/u', $line, $m)) {
                $rows = array_merge($rows, $this->expandMestre((int) $m[1], $m[2], $m[3], $m[4]));
                $pending = null;

                continue;
            }

            // Name that wrapped: "NUM  APELLIDOS, NOMBRE" with no situació yet.
            if (preg_match('/^\s*(\d+)\s+(.+?,\s*.+)$/u', $line, $m) && ! preg_match('/\b('.self::SITUACIO.')\b/u', $line)) {
                $pending = ['pos' => (int) $m[1], 'name' => $m[2]];

                continue;
            }

            // Continuation line beginning with the situació, completing a wrap.
            if ($pending && preg_match('/^\s*('.self::SITUACIO.')\b(.*)$/u', $line, $m)) {
                $rows = array_merge($rows, $this->expandMestre($pending['pos'], $pending['name'], $m[1], $m[2]));
                $pending = null;
            }
        }

        return $rows;
    }

    /**
     * Build one row per habilitation token found in the trailing columns.
     *
     * @return array<int, array<string, mixed>>
     */
    private function expandMestre(int $pos, string $nombre, string $situacio, string $tail): array
    {
        $estado = $this->normalizeSituacio($situacio);
        $tail = str_replace('(*)', ' ', $tail);

        $habs = [];
        foreach (preg_split('/\s+/', trim($tail)) as $tok) {
            $tok = strtoupper(trim($tok));
            if ($tok !== '' && isset(self::MESTRE_HABILITACIONS[$tok])) {
                [$code, $name] = self::MESTRE_HABILITACIONS[$tok];
                $habs[$code] = $name; // dedupe by code
            }
        }

        if (empty($habs)) {
            return [$this->emptyRow($pos, $nombre, $estado)];
        }

        $out = [];
        foreach ($habs as $code => $name) {
            $row = $this->emptyRow($pos, $nombre, $estado);
            $row['especialidad_codigo'] = $code;
            $row['especialidad_nombre'] = $name;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Provisional sectioned list (secundaria + FP): rows grouped under
     * "(CODI) NOM ESPECIALITAT" headers; each participant belongs to the
     * current section's specialty. Positions restart per section.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseSeccionado(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $n = count($lines);
        $rows = [];
        $currentCode = null;
        $currentName = null;

        for ($i = 0; $i < $n; $i++) {
            $line = rtrim($lines[$i]);
            if (trim($line) === '') {
                continue;
            }

            // Specialty section header: "(218) ORIENTACIÓ EDUCATIVA   Col·lectiu".
            if (preg_match('/^\s*\(([0-9A-Z]{2,4})\)\s+(\p{Lu}.*?)\s*$/u', $line, $m)) {
                $currentCode = $m[1];
                $currentName = trim(preg_replace('/\s{2,}Col·lectiu.*$/u', '', $m[2]));

                continue;
            }

            // Participant row under the current section.
            if ($currentCode && preg_match('/^\s*(\d+)\s+(.+?,\s*.+?)\s{2,}('.self::SITUACIO.')\b/u', $line, $m)) {
                $row = $this->emptyRow((int) $m[1], $m[2], $this->normalizeSituacio($m[3]));
                $row['especialidad_codigo'] = $currentCode;
                $row['especialidad_nombre'] = $currentName;
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Canonicalise a provisional-list situació to its Valencian form.
     */
    private function normalizeSituacio(string $raw): string
    {
        $raw = strtoupper(trim($raw));

        return match (true) {
            str_starts_with($raw, 'AMB') => 'AMB SERVEIS',
            str_starts_with($raw, 'SENSE') => 'SENSE SERVEIS',
            str_starts_with($raw, 'BAIXA') => 'BAIXA',
            default => $raw,
        };
    }

    /**
     * Legacy adjudication-list parser. Each participant line begins with a
     * position number, then "APELLIDO1 APELLIDO2, NOMBRE", then a status. When
     * the status is "Adjudicat", the rest carries the adjudication detail:
     *   LLOC  LOCALITAT(CODI) NOM CENTRE  CODESP / NOM ESPECIALITAT  JORNADA
     *
     * @return array<int, array<string, mixed>>
     */
    public function parseAdjudicacions(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $n = count($lines);
        $rows = [];

        for ($i = 0; $i < $n; $i++) {
            $line = trim($lines[$i]);
            if ($line === '') {
                continue;
            }

            if (! preg_match('/^(\d+)\s+(.+?,\s*.+?)\s+('.self::STATUS.')\b(.*)$/iu', $line, $m)) {
                continue;
            }

            [, $posicion, $nombre, $estadoRaw, $rest] = $m;
            $estado = $this->normalizeEstado($estadoRaw);
            $row = $this->emptyRow((int) $posicion, $nombre, $estado);

            if ($estado === 'Adjudicat') {
                $tail = trim($rest);

                // The adjudication detail may continue on the following lines
                // (lloc / localitat(codi) centre / codesp / jornada). Gather
                // them until the next participant row.
                if ($tail === '') {
                    $block = [];
                    $j = $i + 1;
                    while ($j < $n) {
                        $next = trim($lines[$j]);
                        if ($next === '') {
                            $j++;

                            continue;
                        }
                        if (preg_match('/^\d+\s+.+?,\s*.+?\s+('.self::STATUS.')\b/iu', $next)) {
                            break; // start of the next participant
                        }
                        $block[] = $next;
                        $j++;
                    }
                    // Join with 2 spaces so each line becomes its own column.
                    $tail = trim(implode('  ', $block));
                    $i = $j - 1;
                }

                if ($tail !== '') {
                    $row = array_merge($row, $this->parseAdjudicacio($tail));
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Canonicalise a legacy status token to the Valencian form used across the app.
     */
    private function normalizeEstado(string $raw): string
    {
        return match (true) {
            (bool) preg_match('/^desactiv/iu', $raw) => 'Desactivat',
            (bool) preg_match('/^adjudic/iu', $raw) => 'Adjudicat',
            (bool) preg_match('/^activ/iu', $raw) => 'Activat',
            default => ucfirst(mb_strtolower($raw)),
        };
    }

    /**
     * Parse the adjudication tail of an "Adjudicat" line.
     *
     * @return array<string, mixed>
     */
    private function parseAdjudicacio(string $rest): array
    {
        $out = [
            'lloc_adjudicado' => null,
            'centro_nombre' => null,
            'centro_codigo' => null,   // resolved for user_historial; not persisted to participantes_proceso
            'localitat' => null,
            'especialidad_codigo' => null,
            'jornada' => null,
        ];

        // Columns are separated by 2+ spaces in pdftotext -layout output.
        $cols = preg_split('/\s{2,}/', $rest);

        // Leading numeric token = lloc code.
        if (isset($cols[0]) && preg_match('/^\d+$/', trim($cols[0]))) {
            $out['lloc_adjudicado'] = trim(array_shift($cols));
        }

        foreach ($cols as $col) {
            $col = trim($col);

            // LOCALITAT(CODI) NOM CENTRE
            if (preg_match('/^(.+?)\((\d{4,8})\)\s*(.*)$/u', $col, $cm)) {
                $out['localitat'] = trim($cm[1]);
                $out['centro_codigo'] = trim($cm[2]);
                $out['centro_nombre'] = trim($cm[3]) !== '' ? trim($cm[3]) : null;

                continue;
            }

            // CODESP / NOM ESPECIALITAT
            if (preg_match('#^([0-9A-Z]{2,4})\s*/\s*(.+)$#u', $col, $em)) {
                $out['especialidad_codigo'] = $em[1];

                continue;
            }

            // Anything mentioning jornada.
            if (preg_match('/jornada|completa|parcial|temps parcial/iu', $col)) {
                $out['jornada'] = $col;
            }
        }

        return $out;
    }

    private function normalizeName(string $name): string
    {
        return preg_replace('/\s+/', ' ', trim($name));
    }

    /**
     * Identity of a participant row across imports: person + especialidad.
     *
     * @param  array<string, mixed>  $r
     */
    private function diffKey(array $r): string
    {
        return mb_strtolower(trim((string) ($r['nombre_gva'] ?? ''))).'|'.($r['especialidad_codigo'] ?? '');
    }

    /**
     * Stable content signature of a participant row (for change detection):
     * position, status and any adjudication.
     *
     * @param  array<string, mixed>  $r
     */
    private function signature(array $r): string
    {
        return implode('|', [
            (int) ($r['posicion'] ?? 0),
            $r['estado'] ?? '',
            $r['lloc_adjudicado'] ?? '',
        ]);
    }

    /**
     * Match participants to users by nombre_gva and update their bolsa data.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function matchToUsers(Proceso $proceso, array $rows): int
    {
        $updates = 0;

        // Specialty codes collide across cuerpos (e.g. code 120 exists for both
        // Maestros and another body), so scope resolution to this proceso's
        // cuerpo when we know it.
        $bodies = self::CUERPO_BODIES[strtoupper((string) optional($proceso->colectivo)->body)] ?? null;
        $specialtyByCode = [];
        $centroByCode = [];

        // Preload all teachers keyed by lower-cased nombre_gva, so we resolve in
        // memory instead of running a LOWER() full-table scan per row (a 30k-row
        // list would otherwise hit `users` 30k times).
        $usersByName = User::whereNotNull('nombre_gva')->get()
            ->groupBy(fn ($u) => mb_strtolower(trim((string) $u->nombre_gva)));

        foreach ($rows as $r) {
            $users = $usersByName->get(mb_strtolower(trim((string) $r['nombre_gva'])));
            if (! $users || $users->isEmpty()) {
                continue;
            }

            // Resolve a specialty from the adjudication / habilitation code,
            // cuerpo-scoped and memoised.
            $code = $r['especialidad_codigo'];
            $specialtyId = null;
            if ($code) {
                $specialtyId = $specialtyByCode[$code] ??= Specialty::query()
                    ->where(fn ($q) => $q->where('codigo', $code)->orWhere('code', $code))
                    ->when($bodies, fn ($q) => $q->whereIn('body', $bodies))
                    ->value('id');
            }

            if (! $specialtyId) {
                continue;
            }

            // Resolve the adjudicated centre (by GVA code), memoised by code.
            $centroId = null;
            if (! empty($r['centro_codigo'])) {
                $centroId = $centroByCode[$r['centro_codigo']] ??= (Centro::where('codigo', $r['centro_codigo'])->value('id') ?: 0);
                $centroId = $centroId ?: null;
            }
            $esAdjudicat = ($r['estado'] ?? null) === 'Adjudicat';

            foreach ($users as $user) {
                UserEspecialidad::updateOrCreate(
                    ['user_id' => $user->id, 'specialty_id' => $specialtyId, 'anyo' => $proceso->anyo],
                    ['posicion_bolsa' => $r['posicion'], 'estado_bolsa' => $r['estado']],
                );

                // Record the year in the user's history (one row per
                // user+especialidad+año). Adjudication details fill in when present.
                UserHistorial::updateOrCreate(
                    ['user_id' => $user->id, 'specialty_id' => $specialtyId, 'anyo' => $proceso->anyo],
                    [
                        'proceso_id' => $proceso->id,
                        'posicion_definitiva' => $r['posicion'],
                        'estado' => $r['estado'],
                        'centro_adjudicado_id' => $centroId,
                        'lloc_adjudicado' => $r['lloc_adjudicado'] ?? null,
                        'jornada_adjudicada' => $r['jornada'] ?? null,
                        'fecha_adjudicacion' => $esAdjudicat ? now()->toDateString() : null,
                    ],
                );

                $updates++;
            }
        }

        return $updates;
    }
}
