<?php

namespace App\Jobs;

use App\Models\GvaNoticia;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Runs an admin-triggered manual import (a historical/by-URL listing) in the
 * background, recording the outcome on a GvaNoticia so the admin view can show
 * its status. Heavy imports (tens of thousands of rows) shouldn't block a web
 * request, hence a queued job.
 */
class ImportListadoManual implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1200;

    /**
     * @param  'vacantes'|'participantes'|'continua'  $tipo
     */
    public function __construct(
        public readonly int $noticiaId,
        public readonly string $tipo,
        public readonly ?int $procesoId,
    ) {}

    public function handle(): void
    {
        $noticia = GvaNoticia::find($this->noticiaId);
        if (! $noticia) {
            return;
        }

        try {
            $exit = match ($this->tipo) {
                'vacantes' => Artisan::call('vacantes:import-pdf', ['path' => $noticia->url, 'proceso_id' => $this->procesoId]),
                'participantes' => Artisan::call('participantes:import-pdf', ['pdf_path' => $noticia->url, 'proceso_id' => $this->procesoId]),
                'continua' => Artisan::call('adjudicaciones:import-continua', ['path' => $noticia->url]),
                default => 1,
            };

            $output = trim(Str::limit((string) Artisan::output(), 400));
            $noticia->forceFill([
                'importado_en' => now(),
                'import_estado' => $exit === 0 ? 'ok' : 'error',
                'import_resumen' => ($exit === 0 ? 'Importación manual OK. ' : 'Error en la importación. ').$output,
                'proceso_id' => $this->procesoId,
            ])->save();
        } catch (\Throwable $e) {
            Log::error('ImportListadoManual failed', ['url' => $noticia->url, 'error' => $e->getMessage()]);
            $noticia->forceFill(['import_estado' => 'error', 'import_resumen' => 'Error: '.$e->getMessage()])->save();
        }
    }
}
