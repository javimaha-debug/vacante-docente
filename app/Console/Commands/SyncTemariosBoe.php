<?php

namespace App\Console\Commands;

use App\Models\SyncState;
use App\Services\TemarioSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncTemariosBoe extends Command
{
    protected $signature = 'temarios:sync-boe {--cuerpo= : Only sync this cuerpo (maestros|secundaria)} {--enrich : Dispatch AI enrichment jobs after sync (off by default to avoid bulk cost)}';

    protected $description = 'Sync the official BOE temarios (EDU/3136/2011, EDU/3138/2011) from the structured BOE XML.';

    /**
     * Official temario sources: nationwide BOE Orders. We read the structured
     * XML (one <p> per heading/tema, in order) instead of the multi-column PDF,
     * which pdftotext/smalot mangled into noise.
     */
    private const SOURCES = [
        'maestros' => [
            'id' => 'BOE-A-2011-18097',
            'order' => 'EDU/3136/2011',
            'published_at' => '2011-11-18',
        ],
        'secundaria' => [
            'id' => 'BOE-A-2011-18099',
            'order' => 'EDU/3138/2011',
            'published_at' => '2011-11-18',
        ],
    ];

    public function handle(TemarioSyncService $sync): int
    {
        $only = $this->option('cuerpo');
        $enrich = (bool) $this->option('enrich');
        $totals = ['temarios' => 0, 'temas' => 0, 'especialidades' => 0];

        foreach (self::SOURCES as $cuerpo => $src) {
            if ($only && $only !== $cuerpo) {
                continue;
            }

            $xml = $this->fetchXml($src['id']);
            if ($xml === '') {
                $this->warn("No se pudo descargar el XML del BOE de {$cuerpo} ({$src['id']}).");

                continue;
            }

            $especialidades = $this->parseEspecialidades($xml);
            if ($especialidades === []) {
                $this->warn("No se extrajo ninguna especialidad del XML de {$cuerpo}.");

                continue;
            }

            $result = $sync->ingestParsed($cuerpo, $especialidades, [
                'source_url' => "https://www.boe.es/diario_boe/txt.php?id={$src['id']}",
                'source_order' => $src['order'],
                'published_at' => $src['published_at'],
            ], $enrich);

            $totals['especialidades'] += count($especialidades);
            $totals['temarios'] += $result['temarios'];
            $totals['temas'] += $result['temas'];

            $this->line("· {$cuerpo}: ".count($especialidades)." especialidades, {$result['temas']} temas");
        }

        SyncState::record('temarios_boe', $totals);
        $this->info(sprintf('Temarios sincronizados: %d especialidades · %d temas', $totals['especialidades'], $totals['temas']));
        Log::info('temarios:sync-boe done', $totals);

        return self::SUCCESS;
    }

    /** Fetch the BOE disposition as XML. */
    private function fetchXml(string $id): string
    {
        try {
            $response = Http::withHeaders(['Accept' => 'application/xml'])
                ->timeout(60)
                ->get('https://www.boe.es/diario_boe/xml.php', ['id' => $id]);

            return $response->successful() ? $response->body() : '';
        } catch (\Throwable $e) {
            Log::warning('temarios:sync-boe xml fetch failed', ['id' => $id, 'error' => $e->getMessage()]);

            return '';
        }
    }

    /**
     * Parse the BOE XML into especialidades + temas. The temario in these Orders
     * is laid out as: a centered-italic heading per especialidad, then one
     * paragraph per main tema ("N. Título"); subpoints ("N.M …") are ignored.
     *
     * @return array<int, array{especialidad_nombre:string, temas:array<int,array{numero:int,titulo:string}>}>
     */
    public function parseEspecialidades(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $especialidades = [];
        $current = null;

        $commit = function () use (&$especialidades, &$current) {
            if ($current !== null && $current['temas'] !== []) {
                $especialidades[] = $current;
            }
            $current = null;
        };

        foreach ($dom->getElementsByTagName('p') as $p) {
            $class = (string) $p->getAttribute('class');
            $text = trim(preg_replace('/\s+/', ' ', (string) $p->textContent));
            if ($text === '') {
                continue;
            }

            // Especialidad heading: centered-italic, short, no leading "N.".
            if (str_contains($class, 'centro_cursiva')
                && mb_strlen($text) <= 120
                && ! preg_match('/^\d+\./', $text)) {
                $commit();
                $current = ['especialidad_nombre' => $this->cleanName($text), 'temas' => []];

                continue;
            }

            // Main tema: "N. Título" (digit-dot-SPACE). Subpoints "N.M …" don't
            // match because there's no space after the first dot.
            if ($current !== null && preg_match('/^(\d{1,3})\.\s+(\S.*)$/u', $text, $m)) {
                $titulo = $this->cleanTitle($m[2]);
                if ($titulo !== '') {
                    $current['temas'][] = ['numero' => (int) $m[1], 'titulo' => $titulo];
                }
            }
        }
        $commit();

        return $especialidades;
    }

    private function cleanName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', $name), " \t.:");
    }

    private function cleanTitle(string $title): string
    {
        $title = trim(preg_replace('/\s+/', ' ', $title));

        return rtrim($title, " .");
    }
}
