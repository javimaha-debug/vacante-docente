<?php

namespace App\Console\Commands;

use App\Models\Ccaa;
use App\Models\Centro;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class ImportCentrosAnpe extends Command
{
    protected $signature = 'centros:import-anpe
                            {--dir=pdfs/gva : Storage directory holding the ANPE PDFs}
                            {--dry-run : Parse and report without writing}';

    protected $description = 'Import the 7 ANPE centre listings (UECO, CEE, singular, FPA, CRA, penitenciaris, jornada continuada) into centros, marking type and special characteristics.';

    /** filename → [tag, tipo override or null, parser method]. */
    private const SOURCES = [
        'CENTRES_AULES_UECO_2026-27.pdf' => ['UECO', null, 'parseUeco'],
        'CENTRES_EDUCACIO_ESPECIAL_2026-27.pdf' => ['EDUCACIO_ESPECIAL', 'CEE', 'parseCodiFirst'],
        'CENTRES_PUBLICS_CARACTER_SINGULAR_2026-27.pdf' => ['SINGULAR', null, 'parseCodiFirst'],
        'CENTRES_FPA_2026-27.pdf' => ['FPA', 'FPA', 'parseCodiFirst'],
        'CENTRES_CRA_2026-27.pdf' => ['CRA', 'CRA', 'parseCodiFirst'],
        'CENTRES_PENITENCIARIS_2026-27.pdf' => ['PENITENCIARI', 'FPA', 'parsePenitenciaris'],
        'CENTRES_PUBLICS_AUTORITZATS_JORNADA_CONTINUADA_2026-27.pdf' => ['JORNADA_CONTINUA', null, 'parseJornadaContinuada'],
    ];

    public function handle(): int
    {
        $cv = Ccaa::where('code', 'CV')->first();
        if (! $cv) {
            $this->error('CCAA "CV" no encontrada. Ejecuta CcaaSeeder.');

            return self::FAILURE;
        }

        $dir = rtrim((string) $this->option('dir'), '/');
        $anyProcessed = false;

        foreach (self::SOURCES as $file => [$tag, $tipo, $parser]) {
            $rel = "{$dir}/{$file}";
            $full = Storage::path($rel);

            if (! is_file($full)) {
                $this->warn("No encontrado: storage/app/{$rel} — saltando {$tag}.");

                continue;
            }

            $text = $this->extractText($full);
            if ($text === null) {
                continue;
            }

            $records = $this->{$parser}($text);
            $created = 0;
            $tagged = 0;
            $skipped = 0;

            foreach ($records as $r) {
                $codigo = $this->padCode($r['codigo']);
                if ($codigo === null) {
                    continue;
                }

                $centro = Centro::firstOrNew(['codigo' => $codigo]);

                if (! $centro->exists) {
                    if (trim((string) $r['nombre']) === '') {
                        $skipped++;

                        continue; // cannot create a centre without a name
                    }
                    $centro->ccaa_id = $cv->id;
                    $centro->nombre = $r['nombre'];
                    $centro->localidad = $r['localidad'] ?? '';
                    $centro->provincia = $this->provinceFromCode($codigo);
                    $centro->tipo = $tipo ?? $this->inferTipo($r['nombre']);
                    $centro->fuente = 'ANPE';
                    $created++;
                } else {
                    // Backfill missing fields without overwriting good data.
                    if (! $centro->localidad && ! empty($r['localidad'])) {
                        $centro->localidad = $r['localidad'];
                    }
                }

                $caract = collect($centro->caracteristicas ?? []);
                if (! $caract->contains($tag)) {
                    $centro->caracteristicas = $caract->push($tag)->unique()->values()->all();
                    $tagged++;
                }

                if (! $this->option('dry-run')) {
                    $centro->save();
                }
            }

            $anyProcessed = true;
            $this->info("{$tag}: {$created} centros nuevos, {$tagged} marcados, {$skipped} sin nombre (saltados) — de ".count($records)." registros.");
        }

        if (! $anyProcessed) {
            $this->warn("Coloca los PDFs de ANPE en storage/app/{$dir}/ y reintenta.");

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->line('Dry run — nada escrito.');
        }

        return self::SUCCESS;
    }

    private function extractText(string $path): ?string
    {
        $process = new Process(['pdftotext', '-layout', '-enc', 'UTF-8', $path, '-']);
        $process->setTimeout(120);
        try {
            $process->run();
        } catch (\Throwable $e) {
            $this->error('pdftotext no disponible: '.$e->getMessage());

            return null;
        }
        if (! $process->isSuccessful()) {
            $this->error('pdftotext falló: '.trim($process->getErrorOutput()));

            return null;
        }

        return $process->getOutput();
    }

    // ---- Parsers (public for testing) -----------------------------------

    /**
     * Tabular "Codi Centre Adreça Localitat Telèfon" with multi-line wrapping
     * (singular, CEE, FPA, CRA). Records are anchored on a leading 7-8 digit code.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parseCodiFirst(string $text): array
    {
        $records = [];
        $buf = [];

        $flush = function () use (&$buf, &$records) {
            if ($buf === []) {
                return;
            }
            $rec = $this->extractFromBlob(implode('  ', $buf));
            if ($rec) {
                $records[] = $rec;
            }
            $buf = [];
        };

        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $t = rtrim($line);

            // Skip titles, column headers and page footers so they never leak
            // into a record blob.
            if (trim($t) === '' || $this->isNoise($t)) {
                continue;
            }

            $lineHasCode = preg_match('/\b\d{7,8}\b/', $t);

            // A new code line starts a new record: flush the previous one (this
            // covers entries that have no postal "NNNNN - LOCALITAT").
            if ($lineHasCode && preg_match('/\b\d{7,8}\b/', implode('  ', $buf))) {
                $flush();
            }

            $buf[] = $t;
            $blob = implode('  ', $buf);

            // Complete once the buffer holds a code and a "NNNNN - LOCALITAT".
            // This also captures names that wrap onto the line before the code.
            if (preg_match('/\b\d{7,8}\b/', $blob) && preg_match('/\d{5}\s*-\s*[A-Za-zÀ-ÿ]/u', $blob)) {
                $flush();
            }
        }
        $flush();

        return $records;
    }

    private function isNoise(string $line): bool
    {
        return (bool) preg_match('/^\s*(Codi\b|P[àa]g\.|CURS\s+20|LLISTAT|CENTRES\b|CAR[ÀA]CTER|SINGULAR\s*$|FORMACI[ÓO]|RURALS|AGRUPATS|EDUCACI[ÓO]\b|ESPECIAL\s*$|PERSONES|PROV[ÍI]NCIA)/u', $line);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractFromBlob(string $blob): ?array
    {
        if (! preg_match('/\b(\d{7,8})\b/', $blob, $cm)) {
            return null;
        }
        $codigo = $cm[1];

        $localidad = null;
        if (preg_match('/\d{5}\s*-\s*([A-Za-zÀ-ÿ\'’.\/\- ]+?)(?=\s{2,}|\s+\d{9}|$)/u', $blob, $lm)) {
            $localidad = $this->clean($lm[1]);
        }

        // Address keywords that mark the end of the name column.
        $addr = '(?:Calle|Carrer|Av\.|Avda|Avenida|Avinguda|Cam[íi]|Camino|Plaza|Pla[çc]a|Traves[íi]a|Trv\.|C\.|Ctra|Carretera|Partida|Urb|S\/N)';

        $nombre = null;
        if (preg_match('/((?:CEIP|CEE|IES|CRA|EEI|EI|CPEE|CEED|CENTRE|CENTRO|FPA|CEPA|CPFPA|CIPFP|CIFP|ESCOLA|SECCI[ÓO]|COL)\b.*?)(?=\s+'.$addr.'|\s+\d{5}\s*-|\s+\d{9}|$)/u', $blob, $nm)) {
            $nombre = $this->clean($nm[1]);
        } else {
            // Fallback (e.g. private centres "DIOCESANO …"): take the text right
            // after the code up to the address / postal code / phone.
            if (preg_match('/\b\d{7,8}\b\s+([A-Za-zÀ-ÿ][^\d]*?)(?=\s+'.$addr.'|\s+\d{5}\s*-|\s+\d{9}|$)/u', $blob, $fm)) {
                $candidate = $this->clean($fm[1]);
                $nombre = $candidate !== '' ? $candidate : null;
            }
        }

        return ['codigo' => $codigo, 'nombre' => $nombre ?? '', 'localidad' => $localidad];
    }

    /**
     * UECO: "Codi  Centre  Localitat (X / Y)" with province section headers.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parseUeco(string $text): array
    {
        $records = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            if (! preg_match('/^\s*(\d{7,8})\s+(.+?)\s{2,}([A-Za-zÀ-ÿ].*)$/u', $line, $m)) {
                continue;
            }
            $localidad = $this->clean(preg_split('/\s*\/\s*/', trim($m[3]))[0]);
            $records[] = ['codigo' => $m[1], 'nombre' => $this->clean($m[2]), 'localidad' => $localidad];
        }

        return $records;
    }

    /**
     * Jornada continuada: "LOCALITAT  CODI  CENTRE" with province headers.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parseJornadaContinuada(string $text): array
    {
        $records = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            if (! preg_match('/^\s*([A-Za-zÀ-ÿ].*?)\s{2,}(\d{7,8})\s+(.+)$/u', $line, $m)) {
                continue;
            }
            // Skip the column header line.
            if (stripos($m[1], 'LOCALITAT') !== false) {
                continue;
            }
            $records[] = ['codigo' => $m[2], 'nombre' => $this->clean($m[3]), 'localidad' => $this->clean($m[1])];
        }

        return $records;
    }

    /**
     * Penitenciaris: label blocks (Localitat:/Denominació:/Codi:).
     *
     * @return array<int, array<string, mixed>>
     */
    public function parsePenitenciaris(string $text): array
    {
        $records = [];
        $localitat = null;
        $denom = [];
        $capturingDenom = false;

        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $t = trim($line);

            if (preg_match('/^Localitat:\s*(.+)$/u', $t, $m)) {
                $localitat = $this->clean($m[1]);
                $denom = [];
                $capturingDenom = false;
            } elseif (preg_match('/^Denominaci[óo]:\s*(.+)$/u', $t, $m)) {
                $denom = [$this->clean($m[1])];
                $capturingDenom = true;
            } elseif (preg_match('/^Codi:\s*(\d{7,8})/u', $t, $m)) {
                $records[] = [
                    'codigo' => $m[1],
                    'nombre' => $this->clean(implode(' ', $denom)),
                    'localidad' => $localitat,
                ];
                $capturingDenom = false;
            } elseif (preg_match('/^(Adre[çc]a|Composici[óo]|Prov[íi]ncia):/u', $t)) {
                $capturingDenom = false;
            } elseif ($capturingDenom && $t !== '') {
                $denom[] = $this->clean($t); // wrapped denomination line
            }
        }

        return $records;
    }

    // ---- Helpers --------------------------------------------------------

    private function padCode(?string $code): ?string
    {
        $code = preg_replace('/\D/', '', (string) $code);
        if ($code === '' || strlen($code) > 8) {
            return null;
        }

        return str_pad($code, 8, '0', STR_PAD_LEFT);
    }

    private function provinceFromCode(string $codigo): string
    {
        return match (substr($codigo, 0, 2)) {
            '03' => 'Alacant',
            '12' => 'Castelló',
            default => 'València',
        };
    }

    private function inferTipo(string $nombre): string
    {
        $upper = mb_strtoupper($nombre);
        foreach (['CEIP', 'CEE', 'CIPFP', 'CIFP', 'IES', 'CRA', 'EEI', 'EI', 'FPA', 'CEPA', 'CEED'] as $t) {
            if (str_contains($upper, $t)) {
                return $t;
            }
        }

        return 'Otro';
    }

    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value));
    }
}
