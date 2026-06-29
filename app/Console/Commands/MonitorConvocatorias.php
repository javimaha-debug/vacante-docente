<?php

namespace App\Console\Commands;

use App\Models\SyncState;
use App\Models\User;
use App\Notifications\ConvocatoriasDetectadas;
use App\Services\BoeApiService;
use App\Services\ConvocatoriaMonitorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class MonitorConvocatorias extends Command
{
    protected $signature = 'convocatorias:monitor';

    protected $description = 'Monitor BOE + official/union sources for new oposición calls.';

    /** BOE searches (nacional, official → estado anunciada). */
    private const BOE_SEARCHES = [
        'convocatoria oposiciones ingreso cuerpos docentes',
        'procedimiento selectivo maestros',
        'procedimiento selectivo secundaria profesores',
    ];

    public function handle(BoeApiService $boe, ConvocatoriaMonitorService $monitor): int
    {
        $new = 0;
        $found = 0;
        $titulos = [];

        // 1) BOE API — official, nacional.
        foreach (self::BOE_SEARCHES as $query) {
            foreach ($boe->search($query) as $hit) {
                if (! $monitor->looksLikeConvocatoria($hit['titulo'])) {
                    continue;
                }
                $found++;
                $r = $monitor->register($hit['titulo'], 'nacional', 'anunciada', $hit['url_oficial']);
                if ($r['status'] === 'created') {
                    $new++;
                    $titulos[] = $hit['titulo'];
                }
            }
        }

        // 2) Scraping sources. Official gov pages → 'anunciada'; unions → 'rumor'.
        foreach ($this->scrapeSources() as $src) {
            foreach ($monitor->scrape($src['url']) as $cand) {
                $found++;
                $r = $monitor->register($cand['titulo'], $src['comunidad'], $src['estado'], $cand['pdf_url']);
                if ($r['status'] === 'created') {
                    $new++;
                    $titulos[] = $cand['titulo'];
                }
            }
        }

        if ($new > 0) {
            $this->notifySuperadmins($new, $titulos);
        }

        $resumen = ['found' => $found, 'new' => $new];
        SyncState::record('convocatorias_monitor', $resumen);

        $this->info(sprintf('Convocatorias: %d candidatas · %d nuevas', $found, $new));
        Log::info('convocatorias:monitor done', $resumen);

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{url:string, comunidad:string, estado:string}>
     */
    private function scrapeSources(): array
    {
        $configured = config('services.convocatorias.sources');
        if (is_array($configured) && $configured !== []) {
            return $configured;
        }

        return [
            ['url' => 'https://www.educacionyfp.gob.es/servicios-al-ciudadano/catalogo/general/05/059/ficha/059-3261.html', 'comunidad' => 'nacional', 'estado' => 'anunciada'],
            ['url' => 'https://ceice.gva.es/es/web/rrhh-educacion', 'comunidad' => 'valenciana', 'estado' => 'anunciada'],
            ['url' => 'https://anpecomunidadvalenciana.es', 'comunidad' => 'valenciana', 'estado' => 'rumor'],
            ['url' => 'https://ccoo.es/ensenyament', 'comunidad' => 'valenciana', 'estado' => 'rumor'],
        ];
    }

    /**
     * @param  array<int, string>  $titulos
     */
    private function notifySuperadmins(int $nuevas, array $titulos): void
    {
        $admins = User::query()
            ->where('is_admin', true)
            ->orWhere('role', 'superadmin')
            ->orWhere('id', 1)
            ->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new ConvocatoriasDetectadas($nuevas, $titulos));
        }
    }
}
