<?php

namespace App\Console\Commands;

use App\Models\SyncState;
use App\Services\BoeApiService;
use App\Services\NormativaSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncNormativaBoe extends Command
{
    protected $signature = 'normativa:sync-boe';

    protected $description = 'Search the BOE API for key education legislation and populate normativa_documentos.';

    /** Searches to run against the BOE API. */
    private const SEARCHES = [
        'ley organica educacion LOE',
        'LOMLOE ley organica modificacion LOE',
        'Real Decreto curriculo educacion primaria',
        'Real Decreto curriculo ESO bachillerato',
        'Real Decreto curriculo formacion profesional',
        'oposiciones cuerpos docentes ingreso',
    ];

    public function handle(BoeApiService $boe, NormativaSyncService $sync): int
    {
        $found = 0;
        $new = 0;
        $existed = 0;
        $titulos = [];

        foreach (self::SEARCHES as $query) {
            $hits = $boe->search($query);
            foreach ($hits as $hit) {
                $found++;
                $result = $sync->upsertFromHit([
                    'titulo' => $hit['titulo'],
                    'url_oficial' => $hit['url_oficial'],
                    'comunidad_autonoma' => 'nacional',
                    'fecha_publicacion' => $hit['fecha_publicacion'],
                    'fuente' => 'boe',
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
        SyncState::record('normativa_boe', $resumen);

        $this->info(sprintf('BOE: %d encontrados · %d nuevos · %d ya existían', $found, $new, $existed));
        Log::info('normativa:sync-boe done', $resumen);

        return self::SUCCESS;
    }
}
