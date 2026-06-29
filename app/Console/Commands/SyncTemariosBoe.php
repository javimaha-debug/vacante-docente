<?php

namespace App\Console\Commands;

use App\Models\SyncState;
use App\Services\TemarioBoeParser;
use App\Services\TemarioSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class SyncTemariosBoe extends Command
{
    protected $signature = 'temarios:sync-boe {--cuerpo= : Only sync this cuerpo (maestros|secundaria)} {--no-enrich : Skip dispatching AI enrichment}';

    protected $description = 'Download and parse the official BOE temarios (EDU/3136/2011, EDU/3138/2011).';

    /** Official temario sources: nationwide BOE Orders. */
    private const SOURCES = [
        'maestros' => [
            'url' => 'https://www.boe.es/boe/dias/2011/11/18/pdfs/BOE-A-2011-18097.pdf',
            'order' => 'EDU/3136/2011',
            'published_at' => '2011-11-18',
        ],
        'secundaria' => [
            'url' => 'https://www.boe.es/boe/dias/2011/11/18/pdfs/BOE-A-2011-18099.pdf',
            'order' => 'EDU/3138/2011',
            'published_at' => '2011-11-18',
        ],
    ];

    public function handle(TemarioBoeParser $parser, TemarioSyncService $sync): int
    {
        $only = $this->option('cuerpo');
        $enrich = ! $this->option('no-enrich');
        $totals = ['temarios' => 0, 'temas' => 0, 'especialidades' => 0];

        foreach (self::SOURCES as $cuerpo => $src) {
            if ($only && $only !== $cuerpo) {
                continue;
            }

            $path = "temarios/boe/{$cuerpo}-".basename($src['url']);
            if (! $this->download($src['url'], $path)) {
                $this->warn("No se pudo descargar el PDF de {$cuerpo}.");

                continue;
            }

            $text = $this->extractText(Storage::disk('local')->path($path));
            if ($text === '') {
                $this->warn("No se extrajo texto del PDF de {$cuerpo}.");

                continue;
            }

            $especialidades = $parser->parse($text);
            $result = $sync->ingestParsed($cuerpo, $especialidades, [
                'source_url' => $src['url'],
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

    private function download(string $url, string $path): bool
    {
        try {
            $response = Http::timeout(120)->get($url);
            if (! $response->successful()) {
                return false;
            }
            Storage::disk('local')->put($path, $response->body());

            return true;
        } catch (\Throwable $e) {
            Log::warning('temarios:sync-boe download failed', ['url' => $url, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Extract text from a PDF with pdftotext (poppler). Done in an external
     * process so a 700-page BOE order doesn't blow PHP's memory_limit (the
     * smalot/pdfparser approach OOM'd on the secundaria temario).
     */
    private function extractText(string $absolutePath): string
    {
        try {
            $process = new Process(['pdftotext', '-enc', 'UTF-8', $absolutePath, '-']);
            $process->setTimeout(300);
            $process->run();

            if (! $process->isSuccessful()) {
                Log::warning('temarios:sync-boe pdftotext failed', ['path' => $absolutePath, 'stderr' => trim($process->getErrorOutput())]);

                return '';
            }

            return $process->getOutput();
        } catch (\Throwable $e) {
            Log::warning('temarios:sync-boe extract failed', ['path' => $absolutePath, 'error' => $e->getMessage()]);

            return '';
        }
    }
}
