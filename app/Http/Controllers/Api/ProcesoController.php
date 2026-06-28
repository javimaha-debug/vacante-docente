<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\VacancyResource;
use App\Models\Proceso;
use App\Models\ProcesoImportacion;
use App\Models\UserList;
use App\Models\Vacancy;
use App\Services\DistanceCacheRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProcesoController extends Controller
{
    public function __construct(private readonly DistanceCacheRepository $distances) {}

    /**
     * Summary of the most recent import for the "listado actualizado" banner.
     * Only returns a meaningful payload when the last import had changes.
     */
    public function cambios(Proceso $proceso): JsonResponse
    {
        $last = ProcesoImportacion::where('proceso_id', $proceso->id)
            ->orderByDesc('importado_en')
            ->first();

        $hasChanges = $last && ! $last->es_primera
            && ($last->nuevas > 0 || $last->modificadas > 0 || $last->eliminadas > 0);

        return response()->json([
            'has_changes' => (bool) $hasChanges,
            'importado_en' => $last?->importado_en?->toIso8601String(),
            'nuevas' => $last?->nuevas ?? 0,
            'modificadas' => $last?->modificadas ?? 0,
            'eliminadas' => $last?->eliminadas ?? 0,
        ]);
    }

    /**
     * All procesos with estado, colectivo, dates and vacancy counts.
     */
    public function index(): JsonResponse
    {
        $procesos = Proceso::query()
            ->with('colectivo:id,code,name,body')
            ->withCount('vacancies')
            ->orderByDesc('anyo')
            ->orderByRaw("CASE estado WHEN 'publicado' THEN 0 WHEN 'pendiente' THEN 1 ELSE 2 END")
            ->get()
            ->map(fn (Proceso $p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'anyo' => $p->anyo,
                'curso' => $p->curso,
                'estado' => $p->estado,
                'colectivo' => $p->colectivo,
                'ccaa_id' => $p->ccaa_id,
                'fecha_publicacion_vacantes' => $p->fecha_publicacion_vacantes?->toDateString(),
                'fecha_inicio_peticiones' => $p->fecha_inicio_peticiones?->toDateString(),
                'fecha_fin_peticiones' => $p->fecha_fin_peticiones?->toDateString(),
                'fecha_adjudicacion' => $p->fecha_adjudicacion?->toDateString(),
                'vacancies_count' => $p->vacancies_count,
            ]);

        return response()->json(['data' => $procesos]);
    }

    /**
     * Vacancies for a proceso, with the standard explorer filters.
     */
    public function vacantes(Request $request, Proceso $proceso): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'especialidad' => ['sometimes', 'nullable', 'integer', 'exists:specialties,id'],
            'provincia' => ['sometimes', 'in:Alacant,Castelló,València'],
            'localitat' => ['sometimes', 'nullable', 'string', 'max:200'],
            'tipo_centro' => ['sometimes', 'array'],
            'tipo_centro.*' => ['in:Secundaria,Primaria/Infantil,Otro'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:50'],
            'observaciones' => ['sometimes', 'nullable', 'string', 'max:200'],
            'req_ling' => ['sometimes', 'boolean'],
            'itinerante' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'session_token' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        $query = $proceso->vacancies()->getQuery()->with('centro:codigo,caracteristicas');

        if (! empty($validated['especialidad'])) {
            $query->where('specialty_id', $validated['especialidad']);
        }
        if (! empty($validated['provincia'])) {
            $query->where('provincia', $validated['provincia']);
        }
        if (! empty($validated['localitat'])) {
            $query->where('localidad', 'like', '%'.$validated['localitat'].'%');
        }
        if (! empty($validated['tipo_centro'])) {
            $query->whereIn('tipo_centro', $validated['tipo_centro']);
        }
        foreach ($validated['tags'] ?? [] as $tag) {
            $query->withTag($tag);
        }
        if (! empty($validated['observaciones'])) {
            $term = '%'.$validated['observaciones'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('observaciones', 'like', $term)->orWhere('observ', 'like', $term);
            });
        }
        if ($request->boolean('req_ling')) {
            $query->where(fn ($q) => $q->where('requisito_linguistico', true)->orWhere('req_ling', true));
        }
        if ($request->boolean('itinerante')) {
            $query->where('itinerante', true);
        }

        $paginator = $query
            ->orderBy('provincia')
            ->orderBy('localidad')
            ->orderBy('centro_nombre')
            ->paginate((int) ($validated['per_page'] ?? 100))
            ->withQueryString();

        // Attach cached distances (same as the legacy /vacancies endpoint) so
        // vacancies show travel time/distance for organizing.
        $distanceMap = $this->resolveDistances(
            $validated['session_token'] ?? null,
            $validated['especialidad'] ?? null,
            $paginator->getCollection()->modelKeys()
        );

        $paginator->getCollection()->transform(
            fn (Vacancy $vacancy) => (new VacancyResource($vacancy))->withDistances($distanceMap[$vacancy->id] ?? null)
        );

        return VacancyResource::collection($paginator);
    }

    /**
     * Cached distances keyed by vacancy id, resolved from the caller's
     * session list home coordinate.
     *
     * @param  array<int>  $vacancyIds
     * @return array<int, array<string, mixed>>
     */
    private function resolveDistances(?string $sessionToken, ?int $specialtyId, array $vacancyIds): array
    {
        if (! $sessionToken || ! $specialtyId || empty($vacancyIds)) {
            return [];
        }

        $list = UserList::query()
            ->where('session_token', $sessionToken)
            ->where('specialty_id', $specialtyId)
            ->first();

        if (! $list || ! $list->hasHome()) {
            return [];
        }

        return $this->distances->forVacancies($vacancyIds, (float) $list->home_lat, (float) $list->home_lng);
    }
}
