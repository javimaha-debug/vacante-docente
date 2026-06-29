<?php

namespace App\Console\Commands;

use App\Models\SyncState;
use App\Services\NormativaSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncNormativaDogv extends Command
{
    protected $signature = 'normativa:sync-dogv';

    protected $description = 'Scrape the DOGV for Comunitat Valenciana education normativa and populate normativa_documentos.';

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
        .'(KHTML, like Gecko) Chrome/124.0 Safari/537.36 Doccentia-Normativa/1.0';

    public function handle(NormativaSyncService $sync): int
    {
        $found = 0;
        $new = 0;
        $existed = 0;
        $titulos = [];

        foreach ($this->searchUrls() as $url) {
            try {
                $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(30)->get($url);
            } catch (\Throwable $e) {
                Log::warning('normativa:sync-dogv fetch failed', ['url' => $url, 'error' => $e->getMessage()]);

                continue;
            }

            if (! $response->successful()) {
                continue;
            }

            foreach ($sync->extractEducationPdfs($response->body(), $url) as $hit) {
                $found++;
                $result = $sync->upsertFromHit([
                    'titulo' => $hit['titulo'],
                    'url_oficial' => $hit['url_oficial'],
                    'comunidad_autonoma' => 'valenciana',
                    'fuente' => 'dogv',
                    'idioma' => $sync->detectIdioma($hit['titulo']),
                ]);

                if ($result['status'] === 'created') {
                    $new++;
                    $titulos[] = $hit['titulo'];
                } elseif ($result['status'] === 'exists') {
                    $existed++;
                }
            }
        }

        $resumen = ['found' => $found, 'new' => $new, 'existed' => $existed];
        SyncState::record('normativa_dogv', $resumen);

        $this->info(sprintf('DOGV: %d encontrados · %d nuevos · %d ya existían', $found, $new, $existed));
        Log::info('normativa:sync-dogv done', $resumen);

        return self::SUCCESS;
    }

    /**
     * DOGV listing/search pages to scrape. Configurable so the exact endpoints
     * can be tuned without a code change.
     *
     * @return array<int, string>
     */
    private function searchUrls(): array
    {
        $configured = config('services.dogv.search_urls');
        if (is_array($configured) && $configured !== []) {
            return $configured;
        }

        // DOGV search by keyword (text matching on the official gazette index).
        $base = 'https://dogv.gva.es/es/resultat-dogv?materia=&text=';

        return [
            $base.urlencode('decret curriculum ESO'),
            $base.urlencode('decret curriculum batxillerat'),
            $base.urlencode('decret formacio professional'),
            $base.urlencode('instruccions inici de curs'),
            $base.urlencode('resolucio personal docent interins'),
        ];
    }
}
