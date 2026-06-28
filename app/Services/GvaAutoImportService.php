<?php

namespace App\Services;

use App\Models\Colectivo;
use App\Models\GvaNoticia;
use App\Models\ParticipanteImportacion;
use App\Models\Proceso;
use App\Models\ProcesoImportacion;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Downloads a detected GVA listing PDF and imports it automatically, recording
 * the outcome on the GvaNoticia so an admin can review what happened.
 */
class GvaAutoImportService
{
    /**
     * Does this notice point at an importable listing PDF?
     */
    public function isImportable(GvaNoticia $noticia): bool
    {
        return $noticia->tipo === 'PDF'
            && ! $noticia->importado_en
            && $this->resolveKind($noticia->url.' '.$noticia->titulo) !== null;
    }

    /**
     * Resolve a notice to a concrete import target, or null when it can't be
     * mapped confidently (the admin then imports it manually).
     *
     * @return array{kind:string, proceso:Proceso}|null
     */
    public function resolveTarget(GvaNoticia $noticia): ?array
    {
        $text = mb_strtolower($noticia->url.' '.$noticia->titulo);

        $kind = $this->resolveKind($text);
        if ($kind === null) {
            return null;
        }

        $body = $this->resolveBody($text);
        $code = $this->resolveColectivoCode($text);
        $anyo = $this->resolveAnyo($text);

        if (! $body || ! $code || ! $anyo) {
            return null;
        }

        $proceso = Proceso::query()
            ->whereHas('colectivo', fn ($q) => $q->where('code', $code)->where('body', $body))
            ->where('anyo', $anyo)
            ->orderByRaw("CASE estado WHEN 'publicado' THEN 0 WHEN 'pendiente' THEN 1 ELSE 2 END")
            ->first();

        return $proceso ? ['kind' => $kind, 'proceso' => $proceso] : null;
    }

    /**
     * Download + import a notice's PDF. Always records the outcome on the
     * notice. Returns a human-readable summary line.
     */
    public function import(GvaNoticia $noticia): string
    {
        $target = $this->resolveTarget($noticia);

        if (! $target) {
            $noticia->forceFill([
                'import_estado' => 'sin_proceso',
                'import_resumen' => 'No se pudo asociar a un proceso; requiere importación manual.',
            ])->save();

            return $noticia->import_resumen;
        }

        /** @var Proceso $proceso */
        ['kind' => $kind, 'proceso' => $proceso] = $target;

        try {
            $path = $this->download($noticia->url);
        } catch (\Throwable $e) {
            Log::warning('GvaAutoImport: download failed', ['url' => $noticia->url, 'error' => $e->getMessage()]);
            $noticia->forceFill(['import_estado' => 'error', 'import_resumen' => 'No se pudo descargar el PDF.'])->save();

            return $noticia->import_resumen;
        }

        $command = $kind === 'participantes' ? 'participantes:import-pdf' : 'vacantes:import-pdf';
        $args = $kind === 'participantes'
            ? ['pdf_path' => $path, 'proceso_id' => $proceso->id]
            : ['path' => $path, 'proceso_id' => $proceso->id];

        try {
            $exit = Artisan::call($command, $args);
        } catch (\Throwable $e) {
            Log::error('GvaAutoImport: import command threw', ['cmd' => $command, 'error' => $e->getMessage()]);
            $noticia->forceFill(['import_estado' => 'error', 'import_resumen' => 'Error al importar: '.$e->getMessage()])->save();

            return $noticia->import_resumen;
        }

        if ($exit !== 0) {
            $noticia->forceFill(['import_estado' => 'error', 'import_resumen' => 'La importación terminó con errores.'])->save();

            return $noticia->import_resumen;
        }

        $resumen = $this->summariseLastImport($kind, $proceso);

        $noticia->forceFill([
            'importado_en' => now(),
            'import_estado' => 'ok',
            'import_resumen' => $resumen,
            'proceso_id' => $proceso->id,
        ])->save();

        return $resumen;
    }

    /**
     * Build a short change summary from the import row just written.
     */
    private function summariseLastImport(string $kind, Proceso $proceso): string
    {
        if ($kind === 'participantes') {
            $row = ParticipanteImportacion::where('proceso_id', $proceso->id)->orderByDesc('importado_en')->first();
            if (! $row) {
                return "Lista de participantes importada en {$proceso->nombre}.";
            }

            return "Participantes ({$proceso->nombre}): {$row->total} en lista · "
                ."{$row->nuevos} nuevos, {$row->modificados} modificados, {$row->eliminados} eliminados.";
        }

        $row = ProcesoImportacion::where('proceso_id', $proceso->id)->orderByDesc('importado_en')->first();
        if (! $row) {
            return "Listado de vacantes importado en {$proceso->nombre}.";
        }

        return "Vacantes ({$proceso->nombre}): {$row->total} plazas · "
            ."{$row->nuevas} nuevas, {$row->modificadas} modificadas, {$row->eliminadas} eliminadas.";
    }

    /**
     * Download the PDF to the local disk and return its absolute path.
     */
    private function download(string $url): string
    {
        $response = Http::timeout(60)->get($url);
        if (! $response->successful()) {
            throw new \RuntimeException("HTTP {$response->status()}");
        }

        $dir = config('gva.download_dir', 'pdfs/gva/auto');
        $name = basename(parse_url($url, PHP_URL_PATH) ?: 'listado.pdf');
        if (! str_ends_with(mb_strtolower($name), '.pdf')) {
            $name .= '.pdf';
        }
        $relative = $dir.'/'.$name;

        Storage::disk('local')->put($relative, $response->body());

        return Storage::disk('local')->path($relative);
    }

    private function resolveKind(string $text): ?string
    {
        if (preg_match('/participant|llistat de participants|(?<![a-z])par(?![a-z])|_lis_|borsa|bolsa/u', $text)) {
            return 'participantes';
        }
        if (preg_match('/vacant|vacante|(?<![a-z])vac(?![a-z])|llocs/u', $text)) {
            return 'vacantes';
        }

        return null;
    }

    private function resolveBody(string $text): ?string
    {
        if (preg_match('/(?<![a-z])mae(?![a-z])|mestre|maestr/u', $text)) {
            return 'MAESTROS';
        }
        if (preg_match('/(?<![a-z])sec(?![a-z])|secund/u', $text)) {
            return 'SECUNDARIA';
        }

        return null;
    }

    private function resolveColectivoCode(string $text): ?string
    {
        if (preg_match('/interi|interino|(?<![a-z])int(?![a-z])/u', $text)) {
            return 'INTERINO';
        }
        if (preg_match('/suprimit|suprimid|(?<![a-z])supr/u', $text)) {
            return 'SUPRIMIDO';
        }
        if (preg_match('/comiss|comisi|comision/u', $text)) {
            return 'COMISION_SERVICIO';
        }

        return null;
    }

    private function resolveAnyo(string $text): ?int
    {
        if (preg_match('/(?<!\d)(20\d{2})(?!\d)/', $text, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * Available colectivo codes (for documentation / potential validation).
     *
     * @return array<int, string>
     */
    public static function colectivoCodes(): array
    {
        return Colectivo::query()->distinct()->pluck('code')->all();
    }
}
