<?php

namespace App\Console\Commands;

use App\Models\Centro;
use App\Services\GvaCentrosService;
use Illuminate\Console\Command;

class EnrichCentrosGva extends Command
{
    protected $signature = 'centros:enrich-gva
                            {--codigo= : Enrich a single centro by its código de centro}
                            {--limit=0 : Max centros to process (0 = all)}
                            {--only-missing : Only centros without web/telefono/dirección oficial}
                            {--sleep=200 : Milliseconds to wait between requests}';

    protected $description = 'Enrich centros with official contact data (web, teléfono, email, dirección) from the GVA directory.';

    public function handle(GvaCentrosService $service): int
    {
        $query = Centro::query()->orderBy('codigo');

        if ($codigo = $this->option('codigo')) {
            $query->where('codigo', $codigo);
        }
        if ($this->option('only-missing')) {
            $query->where(fn ($q) => $q->whereNull('web')
                ->orWhereNull('telefono')
                ->orWhereNull('direccion_oficial'));
        }
        if (($limit = (int) $this->option('limit')) > 0) {
            $query->limit($limit);
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('No hay centros que enriquecer con esos filtros.');

            return self::SUCCESS;
        }

        $this->info("Enriqueciendo {$total} centros desde el directorio de la GVA…");
        $bar = $this->output->createProgressBar($total);
        $sleepMs = max(0, (int) $this->option('sleep'));

        $updated = 0;
        $notFound = 0;
        $empty = 0;

        $query->chunkById(100, function ($centros) use ($service, &$updated, &$notFound, &$empty, $sleepMs, $bar) {
            foreach ($centros as $centro) {
                $contact = $service->contactData($centro->codigo);

                if ($contact === null) {
                    $notFound++;
                } elseif (empty($contact)) {
                    $empty++;
                } else {
                    $centro->fill($contact)->save();
                    $updated++;
                }

                $bar->advance();

                if ($sleepMs > 0) {
                    usleep($sleepMs * 1000);
                }
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Actualizados: {$updated}");
        $this->line("Sin datos de contacto: {$empty}");
        $this->line("No encontrados en la GVA: {$notFound}");

        return self::SUCCESS;
    }
}
