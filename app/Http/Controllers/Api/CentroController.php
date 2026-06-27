<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\VacancyResource;
use App\Models\Centro;
use App\Models\CentroHorario;
use App\Models\CentroValoracion;
use App\Models\Proceso;
use App\Models\Vacancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class CentroController extends Controller
{
    /**
     * Searchable, filterable school directory with optional proximity search.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tipo' => ['sometimes', 'nullable', 'string', 'max:50'],
            'localidad' => ['sometimes', 'nullable', 'string', 'max:100'],
            'provincia' => ['sometimes', 'nullable', 'string', 'max:50'],
            'query' => ['sometimes', 'nullable', 'string', 'max:200'],
            'lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'radius' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:500'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $query = Centro::query();

        if (! empty($data['tipo'])) {
            $query->where('tipo', $data['tipo']);
        }
        if (! empty($data['localidad'])) {
            $query->where('localidad', 'like', '%'.$data['localidad'].'%');
        }
        if (! empty($data['provincia'])) {
            $query->where('provincia', $data['provincia']);
        }
        if (! empty($data['query'])) {
            $query->where('nombre', 'like', '%'.$data['query'].'%');
        }

        $hasOrigin = isset($data['lat'], $data['lng']);
        $perPage = 20;
        $page = (int) ($data['page'] ?? 1);

        if ($hasOrigin) {
            // Proximity search: compute distances in PHP, filter by radius, sort.
            $collection = $query->orderBy('nombre')->get()
                ->map(function (Centro $c) use ($data) {
                    $c->distance_km = ($c->latitude !== null && $c->longitude !== null)
                        ? round($this->haversine((float) $data['lat'], (float) $data['lng'], (float) $c->latitude, (float) $c->longitude), 1)
                        : null;

                    return $c;
                });

            if (! empty($data['radius'])) {
                $collection = $collection->filter(
                    fn (Centro $c) => $c->distance_km !== null && $c->distance_km <= $data['radius']
                );
            }

            $collection = $collection->sortBy(fn (Centro $c) => $c->distance_km ?? INF)->values();

            $paginator = new LengthAwarePaginator(
                $collection->forPage($page, $perPage)->map(fn (Centro $c) => $this->cardArray($c, true))->values(),
                $collection->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return response()->json($paginator);
        }

        $paginator = $query->orderBy('nombre')->paginate($perPage)->withQueryString();
        $paginator->getCollection()->transform(fn (Centro $c) => $this->cardArray($c, false));

        return response()->json($paginator);
    }

    /**
     * Full centro detail: contact, schedules, aggregated ratings, vacancies.
     */
    public function show(string $codigo): JsonResponse
    {
        $centro = Centro::where('codigo', $codigo)->firstOrFail();

        $horarios = $centro->horarios()->orderByDesc('validaciones')->get()
            ->map(fn (CentroHorario $h) => [
                'id' => $h->id,
                'hora_entrada' => $h->hora_entrada,
                'hora_salida' => $h->hora_salida,
                'hora_entrada_tarde' => $h->hora_entrada_tarde,
                'hora_salida_tarde' => $h->hora_salida_tarde,
                'jornada_continua' => (bool) $h->jornada_continua,
                'dia_libre' => $h->dia_libre,
                'curso_escolar' => $h->curso_escolar,
                'validaciones' => $h->validaciones,
                'notas' => $h->notas,
            ]);

        $valoraciones = $centro->valoraciones();
        $aggregated = [
            'count' => (clone $valoraciones)->count(),
            'puntuacion' => round((float) (clone $valoraciones)->avg('puntuacion'), 2),
            'ambiente_trabajo' => round((float) (clone $valoraciones)->avg('ambiente_trabajo'), 2),
            'equipo_directivo' => round((float) (clone $valoraciones)->avg('equipo_directivo'), 2),
            'instalaciones' => round((float) (clone $valoraciones)->avg('instalaciones'), 2),
            // Anonymous: only comments + curso, never user identity.
            'comentarios' => (clone $valoraciones)->whereNotNull('comentario')->latest()->limit(20)
                ->get(['comentario', 'curso_escolar', 'puntuacion'])
                ->map(fn ($v) => [
                    'comentario' => $v->comentario,
                    'curso_escolar' => $v->curso_escolar,
                    'puntuacion' => $v->puntuacion,
                ]),
        ];

        $procesoId = Proceso::where('estado', 'publicado')->orderByDesc('anyo')->value('id');
        $vacantes = [];
        if ($procesoId) {
            $vacantes = VacancyResource::collection(
                Vacancy::where('proceso_id', $procesoId)
                    ->where(fn ($q) => $q->where('codi_centre', $codigo)->orWhere('centro_codigo', $codigo))
                    ->get()
            );
        }

        return response()->json([
            'centro' => $this->cardArray($centro, false) + [
                'direccion' => $centro->direccion,
                'telefono' => $centro->telefono,
                'email' => $centro->email,
                'web' => $centro->web,
                'latitude' => $centro->latitude,
                'longitude' => $centro->longitude,
                'etapas' => $centro->etapas,
                'bilingue' => (bool) $centro->bilingue,
                'datos_verificados' => (bool) $centro->datos_verificados,
            ],
            'horarios' => $horarios,
            'valoraciones' => $aggregated,
            'vacantes' => $vacantes,
        ]);
    }

    /**
     * Community-contributed schedule. Dedupes by matching times: an identical
     * schedule for the same curso increments its validation counter instead of
     * creating a duplicate.
     */
    public function storeHorario(Request $request, string $codigo): JsonResponse
    {
        $centro = Centro::where('codigo', $codigo)->firstOrFail();

        $data = $request->validate([
            'hora_entrada' => ['nullable', 'date_format:H:i'],
            'hora_salida' => ['nullable', 'date_format:H:i'],
            'hora_entrada_tarde' => ['nullable', 'date_format:H:i'],
            'hora_salida_tarde' => ['nullable', 'date_format:H:i'],
            'jornada_continua' => ['sometimes', 'boolean'],
            'dia_libre' => ['nullable', 'string', 'max:20'],
            'curso_escolar' => ['required', 'string', 'max:20'],
            'notas' => ['nullable', 'string'],
        ]);

        $user = $request->user();

        // Same times already reported by someone else for this curso → validate it.
        $existing = CentroHorario::where('centro_id', $centro->id)
            ->where('curso_escolar', $data['curso_escolar'])
            ->where('hora_entrada', $data['hora_entrada'] ?? null)
            ->where('hora_salida', $data['hora_salida'] ?? null)
            ->where('user_id', '!=', $user->id)
            ->first();

        if ($existing) {
            $validados = collect($existing->validado_por ?? []);
            if (! $validados->contains($user->id)) {
                $existing->increment('validaciones');
                $existing->validado_por = $validados->push($user->id)->unique()->values()->all();
                $existing->save();
            }

            return response()->json($existing->fresh(), 200);
        }

        $horario = CentroHorario::updateOrCreate(
            ['centro_id' => $centro->id, 'user_id' => $user->id, 'curso_escolar' => $data['curso_escolar']],
            [
                'hora_entrada' => $data['hora_entrada'] ?? null,
                'hora_salida' => $data['hora_salida'] ?? null,
                'hora_entrada_tarde' => $data['hora_entrada_tarde'] ?? null,
                'hora_salida_tarde' => $data['hora_salida_tarde'] ?? null,
                'jornada_continua' => $data['jornada_continua'] ?? false,
                'dia_libre' => $data['dia_libre'] ?? null,
                'notas' => $data['notas'] ?? null,
                'validaciones' => 1,
                'validado_por' => [$user->id],
            ],
        );

        return response()->json($horario, 201);
    }

    /**
     * Community rating, one per user+centro+curso (upsert).
     */
    public function storeValoracion(Request $request, string $codigo): JsonResponse
    {
        $centro = Centro::where('codigo', $codigo)->firstOrFail();

        $data = $request->validate([
            'puntuacion' => ['required', 'integer', 'between:1,5'],
            'ambiente_trabajo' => ['nullable', 'integer', 'between:1,5'],
            'equipo_directivo' => ['nullable', 'integer', 'between:1,5'],
            'instalaciones' => ['nullable', 'integer', 'between:1,5'],
            'comentario' => ['nullable', 'string'],
            'es_anonima' => ['sometimes', 'boolean'],
            'curso_escolar' => ['required', 'string', 'max:20'],
        ]);

        $valoracion = CentroValoracion::updateOrCreate(
            ['centro_id' => $centro->id, 'user_id' => $request->user()->id, 'curso_escolar' => $data['curso_escolar']],
            [
                'puntuacion' => $data['puntuacion'],
                'ambiente_trabajo' => $data['ambiente_trabajo'] ?? null,
                'equipo_directivo' => $data['equipo_directivo'] ?? null,
                'instalaciones' => $data['instalaciones'] ?? null,
                'comentario' => $data['comentario'] ?? null,
                'es_anonima' => $data['es_anonima'] ?? true,
            ],
        );

        return response()->json($valoracion, 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function cardArray(Centro $centro, bool $withDistance): array
    {
        $card = [
            'id' => $centro->id,
            'codigo' => $centro->codigo,
            'nombre' => $centro->nombre,
            'tipo' => $centro->tipo,
            'localidad' => $centro->localidad,
            'provincia' => $centro->provincia,
            'telefono' => $centro->telefono,
        ];

        if ($withDistance) {
            $card['distance_km'] = $centro->distance_km ?? null;
        }

        return $card;
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
