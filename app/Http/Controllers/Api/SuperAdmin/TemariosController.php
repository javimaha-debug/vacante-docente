<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateTemarioEnrichmentJob;
use App\Models\SyncState;
use App\Models\TemaOficial;
use App\Models\TemarioOficial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class TemariosController extends Controller
{
    /**
     * List official temarios with enrichment progress, filterable by cuerpo/search.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cuerpo' => ['sometimes', 'nullable', 'in:maestros,secundaria,fp,otros'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $temarios = TemarioOficial::query()
            ->when($data['cuerpo'] ?? null, fn ($q, $c) => $q->where('cuerpo', $c))
            ->when($data['search'] ?? null, fn ($q, $s) => $q->where('especialidad_nombre', 'like', "%{$s}%"))
            ->withCount([
                'temas',
                'temas as enriquecidos_count' => fn ($q) => $q->whereNotNull('generated_at'),
            ])
            ->orderBy('cuerpo')->orderBy('especialidad_nombre')
            ->get();

        return response()->json([
            'data' => $temarios->map(fn (TemarioOficial $t) => [
                'id' => $t->id,
                'cuerpo' => $t->cuerpo,
                'especialidad_code' => $t->especialidad_code,
                'especialidad_nombre' => $t->especialidad_nombre,
                'source_order' => $t->source_order,
                'total_temas' => $t->temas_count,
                'enriquecidos' => $t->enriquecidos_count,
                'pct_enriquecido' => $t->temas_count > 0 ? (int) round($t->enriquecidos_count / $t->temas_count * 100) : 0,
                'last_synced_at' => $t->last_synced_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * The temas of a temario (with esquema preview).
     */
    public function temas(TemarioOficial $temario): JsonResponse
    {
        $temas = $temario->temas()->orderBy('numero')->get();

        return response()->json([
            'temario' => [
                'id' => $temario->id,
                'especialidad_nombre' => $temario->especialidad_nombre,
                'cuerpo' => $temario->cuerpo,
                'source_order' => $temario->source_order,
            ],
            'data' => $temas->map(fn (TemaOficial $t) => $this->temaArray($t)),
        ]);
    }

    /**
     * Add a custom tema not present in the BOE.
     */
    public function storeTema(Request $request, TemarioOficial $temario): JsonResponse
    {
        $data = $request->validate([
            'numero' => ['required', 'integer', 'min:1'],
            'titulo' => ['required', 'string', 'max:500'],
        ]);

        $tema = TemaOficial::updateOrCreate(
            ['temario_id' => $temario->id, 'numero' => $data['numero']],
            ['titulo' => $data['titulo']],
        );
        $temario->update(['total_temas' => $temario->temas()->count()]);

        return response()->json($this->temaArray($tema), 201);
    }

    /**
     * Inline-edit a tema's title / esquema / bibliografía.
     */
    public function updateTema(Request $request, TemaOficial $tema): JsonResponse
    {
        $data = $request->validate([
            'titulo' => ['sometimes', 'string', 'max:500'],
            'esquema' => ['sometimes', 'nullable', 'array'],
            'bibliografia' => ['sometimes', 'nullable', 'array'],
            'keywords' => ['sometimes', 'nullable', 'array'],
            'tiempo_estimado_minutos' => ['sometimes', 'nullable', 'integer'],
        ]);

        $tema->fill($data)->save();

        return response()->json($this->temaArray($tema));
    }

    public function destroyTema(TemaOficial $tema): JsonResponse
    {
        $temario = $tema->temario;
        $tema->delete();
        $temario?->update(['total_temas' => $temario->temas()->count()]);

        return response()->json(['deleted' => true]);
    }

    /**
     * Re-run AI enrichment for the whole temario.
     */
    public function regenerate(TemarioOficial $temario): JsonResponse
    {
        GenerateTemarioEnrichmentJob::dispatch($temario->id, force: true);

        return response()->json(['queued' => true]);
    }

    /**
     * Run the BOE temario sync synchronously.
     */
    public function syncBoe(): JsonResponse
    {
        $exit = Artisan::call('temarios:sync-boe');

        return response()->json([
            'ran' => $exit === 0,
            'output' => trim(Artisan::output()),
            'state' => optional(SyncState::where('clave', 'temarios_boe')->first())->resumen,
        ]);
    }

    /**
     * Aggregate stats for the temarios dashboard header.
     */
    public function stats(): JsonResponse
    {
        $totalTemas = TemaOficial::count();
        $conEsquema = TemaOficial::whereNotNull('esquema')->count();
        $conBib = TemaOficial::whereNotNull('bibliografia')->count();
        $lastEnrich = SyncState::where('clave', 'temario_enrichment')->first();

        return response()->json([
            'total_especialidades' => TemarioOficial::count(),
            'total_temas' => $totalTemas,
            'pct_esquema' => $totalTemas > 0 ? (int) round($conEsquema / $totalTemas * 100) : 0,
            'pct_bibliografia' => $totalTemas > 0 ? (int) round($conBib / $totalTemas * 100) : 0,
            'ultima_generacion' => $lastEnrich?->last_run_at?->toIso8601String(),
            'coste_estimado_usd' => $lastEnrich?->resumen['coste_estimado_usd'] ?? null,
            'last_sync_boe' => optional(SyncState::where('clave', 'temarios_boe')->first())->last_run_at?->toIso8601String(),
        ]);
    }

    /** @return array<string, mixed> */
    private function temaArray(TemaOficial $t): array
    {
        return [
            'id' => $t->id,
            'numero' => $t->numero,
            'titulo' => $t->titulo,
            'esquema' => $t->esquema,
            'bibliografia' => $t->bibliografia,
            'keywords' => $t->keywords,
            'tiempo_estimado_minutos' => $t->tiempo_estimado_minutos,
            'enriquecido' => $t->generated_at !== null,
            'generated_at' => $t->generated_at?->toIso8601String(),
        ];
    }
}
