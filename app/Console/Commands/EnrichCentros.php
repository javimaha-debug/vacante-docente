<?php

namespace App\Console\Commands;

use App\Models\Centro;
use App\Services\GvaCentrosService;
use Illuminate\Console\Command;

class EnrichCentros extends Command
{
    protected $signature = 'centros:enrich
                            {--codigo= : Enrich a single centro by its code}
                            {--limit=0 : Max centros to process (0 = all)}
                            {--only-missing : Only centros not yet verified or without coordinates}
                            {--sleep=200 : Milliseconds to wait between API calls}
                            {--debug : Dump the raw GVA payload of the first centro}';

    protected $description = 'Enrich centros with fresh data from the GVA official directory REST API (+ geocoding fallback).';

    public function handle(GvaCentrosService $service): int
    {
        $query = Centro::query()->orderBy('codigo');

        if ($codigo = $this->option('codigo')) {
            $query->where('codigo', $codigo);
        }
        if ($this->option('only-missing')) {
            $query->where(fn ($q) => $q->where('datos_verificados', false)->orWhereNull('latitude'));
        }
        if (($limit = (int) $this->option('limit')) > 0) {
            $query->limit($limit);
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('No hay centros que enriquecer con esos filtros.');

            return self::SUCCESS;
        }

        $this->info("Enriqueciendo {$total} centros desde la API de la GVA…");
        $bar = $this->output->createProgressBar($total);
        $sleepMs = max(0, (int) $this->option('sleep'));
        $debug = (bool) $this->option('debug');

        $counts = ['updated' => 0, 'geocoded' => 0, 'not_found' => 0, 'empty' => 0];
        $first = true;

        $query->chunkById(100, function ($centros) use ($service, &$counts, &$first, $debug, $sleepMs, $bar) {
            foreach ($centros as $centro) {
                $res = $service->enrich($centro, $debug && $first);
                $counts[$res['status']] = ($counts[$res['status']] ?? 0) + 1;
                if ($res['geocoded']) {
                    $counts['geocoded']++;
                }
                if ($debug && $first && ! empty($res['raw'])) {
                    $this->newLine();
                    $this->line('Payload GVA de '.$centro->codigo.':');
                    $this->line(json_encode($res['raw'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }
                $first = false;
                $bar->advance();

                if ($sleepMs > 0) {
                    usleep($sleepMs * 1000);
                }
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Actualizados: {$counts['updated']} (de ellos geocodificados: {$counts['geocoded']}) · "
            ."no encontrados: {$counts['not_found']} · sin datos: {$counts['empty']}");

        return self::SUCCESS;
    }
}
