<?php

namespace App\Console\Commands;

use App\Jobs\GenerateTemarioEnrichmentJob;
use App\Models\TemaOficial;
use App\Models\TemarioOficial;
use Illuminate\Console\Command;

class EnrichTemarios extends Command
{
    protected $signature = 'temarios:enrich
        {--especialidad= : Nombre o código de la especialidad (parcial, case-insensitive)}
        {--cuerpo= : Filtrar por cuerpo (maestros|secundaria)}
        {--force : Re-enriquecer temas ya procesados}
        {--confirm : Omitir la guardia de coste y ejecutar aunque supere el límite}
        {--limit=200 : Número máximo de temas a enriquecer sin --confirm}
        {--sync : Ejecutar de forma síncrona (útil en producción sin worker)}';

    protected $description = 'Enriquece temarios oficiales con IA (esquema + bibliografía). Requiere --confirm si supera --limit temas.';

    public function handle(): int
    {
        $especialidad = $this->option('especialidad');
        $cuerpo = $this->option('cuerpo');
        $force = (bool) $this->option('force');
        $confirm = (bool) $this->option('confirm');
        $limit = (int) $this->option('limit');
        $sync = (bool) $this->option('sync');

        $query = TemarioOficial::query();

        if ($cuerpo) {
            $query->where('cuerpo', $cuerpo);
        }

        if ($especialidad) {
            // ILIKE on PostgreSQL, LIKE on SQLite (both work case-insensitively this way).
            $op = \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($especialidad, $op) {
                $q->where('especialidad_nombre', $op, '%'.$especialidad.'%')
                    ->orWhere('especialidad_code', $op, '%'.$especialidad.'%');
            });
        }

        $temarios = $query->get();

        if ($temarios->isEmpty()) {
            $this->warn('No se encontró ningún temario con los filtros indicados.');

            return self::FAILURE;
        }

        // Count temas pending enrichment.
        $pendingCount = $temarios->sum(function (TemarioOficial $t) use ($force) {
            $q = TemaOficial::where('temario_id', $t->id);
            if (! $force) {
                $q->whereNull('generated_at');
            }

            return $q->count();
        });

        $totalTemas = $temarios->sum('total_temas');
        $this->line(sprintf(
            'Temarios encontrados: %d  |  Total temas: %d  |  Pendientes de enriquecer: %d',
            $temarios->count(),
            $totalTemas,
            $pendingCount
        ));

        foreach ($temarios as $t) {
            $this->line("  · [{$t->cuerpo}] {$t->especialidad_nombre} ({$t->total_temas} temas)");
        }

        if ($pendingCount === 0) {
            $this->info('Todos los temas ya están enriquecidos. Usa --force para re-generar.');

            return self::SUCCESS;
        }

        // Cost estimate: 2 calls/tema × ~600 tokens input + ~600 tokens output avg.
        $estimatedUsd = round(($pendingCount * 1200 / 1_000_000) * 3 + ($pendingCount * 1200 / 1_000_000) * 15, 2);
        $this->line(sprintf(
            'Coste estimado: ~$%.2f USD (%d llamadas a Claude Sonnet)',
            $estimatedUsd,
            $pendingCount * 2
        ));

        if ($pendingCount > $limit && ! $confirm) {
            $this->error(sprintf(
                'Se superan los %d temas permitidos (%d pendientes). Usa --confirm para continuar o reduce el alcance con --especialidad / --cuerpo.',
                $limit,
                $pendingCount
            ));

            return self::FAILURE;
        }

        if (! $confirm && ! $this->confirm(sprintf('¿Enriquecer %d temas?', $pendingCount), false)) {
            $this->line('Operación cancelada.');

            return self::SUCCESS;
        }

        foreach ($temarios as $temario) {
            $this->line("Despachando enriquecimiento: {$temario->especialidad_nombre}…");
            if ($sync) {
                GenerateTemarioEnrichmentJob::dispatchSync($temario->id, $force);
            } else {
                GenerateTemarioEnrichmentJob::dispatch($temario->id, $force);
            }
        }

        $this->info(sprintf('%d job(s) despachados.', $temarios->count()));

        return self::SUCCESS;
    }
}
