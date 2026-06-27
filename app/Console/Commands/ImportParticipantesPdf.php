<?php

namespace App\Console\Commands;

use App\Models\ParticipanteProceso;
use App\Models\Proceso;
use App\Models\Specialty;
use App\Models\User;
use App\Models\UserEspecialidad;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class ImportParticipantesPdf extends Command
{
    protected $signature = 'participantes:import-pdf {pdf_path} {proceso_id} {--dry-run}';

    protected $description = 'Parse a GVA participant-list PDF and import positions/status for a proceso.';

    public function handle(): int
    {
        $path = (string) $this->argument('pdf_path');
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
        $this->info('Participantes detectados: '.count($rows));

        if ($this->option('dry-run')) {
            $this->line('Dry run — no se escribe nada.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($proceso, $rows) {
            ParticipanteProceso::where('proceso_id', $proceso->id)->delete();
            foreach (array_chunk($rows, 100) as $chunk) {
                $now = now();
                ParticipanteProceso::insert(array_map(fn ($r) => array_merge($r, [
                    'proceso_id' => $proceso->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]), $chunk));
            }
        });

        $matched = $this->matchToUsers($proceso, $rows);
        $this->info("Importados ".count($rows)." participantes; {$matched} actualizaciones en perfiles de usuario.");

        return self::SUCCESS;
    }

    private function extractText(string $path): ?string
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
     * Parse participant rows. Each participant line begins with a position
     * number, then "APELLIDO1 APELLIDO2, NOMBRE", then a status. When the
     * status is "Adjudicat", the rest of the line carries the adjudication:
     *   LLOC  LOCALITAT(CODI) NOM CENTRE  CODESP / NOM ESPECIALITAT  JORNADA
     *
     * @return array<int, array<string, mixed>>
     */
    /** Status tokens accepted in either Valencian or Spanish. */
    private const STATUS = 'Activat|Desactivat|Adjudicat|Activado|Desactivado|Adjudicado';

    public function parseText(string $text): array
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
            $row = [
                'posicion' => (int) $posicion,
                'nombre_gva' => $this->normalizeName($nombre),
                'estado' => $estado,
                'lloc_adjudicado' => null,
                'centro_nombre' => null,
                'localitat' => null,
                'especialidad_codigo' => null,
                'jornada' => null,
            ];

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
     * Canonicalise a status token to the Valencian form used across the app.
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
     * Match participants to users by nombre_gva and update their bolsa data.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function matchToUsers(Proceso $proceso, array $rows): int
    {
        $updates = 0;

        foreach ($rows as $r) {
            $users = User::whereRaw('LOWER(nombre_gva) = ?', [mb_strtolower($r['nombre_gva'])])->get();
            if ($users->isEmpty()) {
                continue;
            }

            // Resolve a specialty from the adjudication code when present.
            $specialtyId = null;
            if ($r['especialidad_codigo']) {
                $specialtyId = Specialty::where('codigo', $r['especialidad_codigo'])
                    ->orWhere('code', $r['especialidad_codigo'])
                    ->value('id');
            }

            if (! $specialtyId) {
                continue;
            }

            foreach ($users as $user) {
                UserEspecialidad::updateOrCreate(
                    ['user_id' => $user->id, 'specialty_id' => $specialtyId, 'anyo' => $proceso->anyo],
                    ['posicion_bolsa' => $r['posicion'], 'estado_bolsa' => $r['estado']],
                );
                $updates++;
            }
        }

        return $updates;
    }
}
