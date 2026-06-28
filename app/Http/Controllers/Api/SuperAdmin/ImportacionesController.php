<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AdjudicacionContinua;
use App\Models\GvaNoticia;
use App\Models\ParticipanteImportacion;
use App\Models\ProcesoImportacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Health of the GVA auto-import pipeline for the super-admin panel: did the
 * monitor run, what was detected, what imported OK / errored, and a button to
 * run the monitor on demand.
 */
class ImportacionesController extends Controller
{
    public function health(): JsonResponse
    {
        return response()->json($this->snapshot());
    }

    /**
     * Run the GVA monitor synchronously and return the fresh health snapshot.
     */
    public function runMonitor(): JsonResponse
    {
        $exit = Artisan::call('gva:monitor');

        return response()->json([
            'ran' => $exit === 0,
            'output' => trim(Artisan::output()),
            'health' => $this->snapshot(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        $ultima = GvaNoticia::max('created_at');

        $resumen = [
            'ultima_deteccion' => $ultima,
            'total' => GvaNoticia::count(),
            'pendientes' => GvaNoticia::where('tipo', 'PDF')->whereNull('import_estado')->count(),
            'importadas' => GvaNoticia::where('import_estado', 'ok')->count(),
            'errores' => GvaNoticia::where('import_estado', 'error')->count(),
            'sin_proceso' => GvaNoticia::where('import_estado', 'sin_proceso')->count(),
        ];

        $noticias = GvaNoticia::orderByDesc('created_at')->limit(25)->get()
            ->map(fn (GvaNoticia $n) => [
                'id' => $n->id,
                'titulo' => $n->titulo,
                'tipo' => $n->tipo,
                'url' => $n->url,
                'estado' => $n->import_estado ?? 'pendiente',
                'resumen' => $n->import_resumen,
                'importado_en' => $n->importado_en?->toIso8601String(),
                'detectado_en' => $n->created_at?->toIso8601String(),
            ]);

        $vacantes = ProcesoImportacion::with('proceso:id,nombre')
            ->orderByDesc('importado_en')->limit(10)->get()
            ->map(fn ($i) => [
                'tipo' => 'vacantes',
                'proceso' => $i->proceso?->nombre,
                'fecha' => $i->importado_en?->toIso8601String(),
                'total' => $i->total,
                'nuevas' => $i->nuevas, 'modificadas' => $i->modificadas, 'eliminadas' => $i->eliminadas,
                'es_primera' => (bool) $i->es_primera,
            ]);

        $participantes = ParticipanteImportacion::with('proceso:id,nombre')
            ->orderByDesc('importado_en')->limit(10)->get()
            ->map(fn ($i) => [
                'tipo' => 'participantes',
                'proceso' => $i->proceso?->nombre,
                'fecha' => $i->importado_en?->toIso8601String(),
                'total' => $i->total,
                'nuevas' => $i->nuevos, 'modificadas' => $i->modificados, 'eliminadas' => $i->eliminados,
                'es_primera' => (bool) $i->es_primera,
            ]);

        // Continua imports grouped by tanda (fecha + cuerpo).
        $continua = AdjudicacionContinua::select('fecha', 'cuerpo', 'curso', DB::raw('count(*) as filas'))
            ->groupBy('fecha', 'cuerpo', 'curso')
            ->orderByDesc('fecha')->limit(10)->get()
            ->map(fn ($t) => [
                'tipo' => 'continua',
                'proceso' => 'Adjudicació contínua '.$t->cuerpo,
                'fecha' => $t->fecha?->toDateString(),
                'total' => (int) $t->filas,
                'curso' => $t->curso,
            ]);

        return [
            'resumen' => $resumen,
            'noticias' => $noticias,
            'importaciones' => $vacantes->concat($participantes)->concat($continua)
                ->sortByDesc('fecha')->values()->take(20),
        ];
    }
}
