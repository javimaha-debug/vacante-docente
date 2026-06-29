<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\VacancyResource;
use App\Models\AcademicCalendarEvent;
use App\Models\OposicionTema;
use App\Models\Proceso;
use App\Models\User;
use App\Models\UserEspecialidad;
use App\Models\UserList;
use App\Models\Vacancy;
use App\Services\GoogleMapsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UserProfileController extends Controller
{
    public function __construct(private readonly GoogleMapsService $maps) {}

    /**
     * The authenticated user with the relationships the SPA boots with.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['especialidades.specialty', 'ccaa', 'colectivo']);
        $impersonation = $this->impersonationState();

        return response()->json(array_merge($user->toArray(), [
            'features' => app(\App\Policies\FeaturePolicy::class)->featureMap($user),
            'plan_label' => $user->planLabel(),
            'plan_status_label' => $user->planStatusLabel(),
            'is_paid' => $user->isPaid(),
            'is_admin' => $user->isAdmin(),
            'is_superadmin' => $user->isSuperAdmin(),
            'is_impersonated' => $impersonation['active'],
            'impersonated_by' => $impersonation['by'],
        ]));
    }

    /**
     * Full teacher profile payload.
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->profilePayload($request->user()));
    }

    /**
     * Update editable profile fields; geocode the home address when it changes.
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre_gva' => ['sometimes', 'nullable', 'string', 'max:200'],
            'direccion_origen' => ['sometimes', 'nullable', 'string', 'max:300'],
            'lat_origen' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'lng_origen' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'locale' => ['sometimes', 'in:es,ca'],
            'notificaciones_email' => ['sometimes', 'boolean'],
            'colectivo_id' => ['sometimes', 'nullable', 'integer', 'exists:colectivos,id'],
            'ccaa_id' => ['sometimes', 'nullable', 'integer', 'exists:ccaas,id'],
        ]);

        $user = $request->user();
        $addressChanged = array_key_exists('direccion_origen', $data)
            && $data['direccion_origen'] !== $user->direccion_origen;

        // Coordinates verified client-side (a suggestion was selected) take
        // precedence; only the address text fields get mass-assigned otherwise.
        $coordsProvided = array_key_exists('lat_origen', $data) && array_key_exists('lng_origen', $data)
            && $data['lat_origen'] !== null && $data['lng_origen'] !== null;

        $user->fill(collect($data)->except(['lat_origen', 'lng_origen'])->all());

        if ($coordsProvided) {
            $user->lat_origen = $data['lat_origen'];
            $user->lng_origen = $data['lng_origen'];
        } elseif ($addressChanged) {
            // Fallback: geocode server-side when no verified coordinates given.
            $this->geocodeHomeAddress($user, $data['direccion_origen']);
        }

        $user->save();

        return response()->json($this->profilePayload($user->fresh()));
    }

    /**
     * Switch the user's active mode (bolsa / oposicion / docente). Drives the
     * sidebar and which tools are surfaced.
     */
    public function updateModo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'modo_activo' => ['required', 'in:bolsa,oposicion,docente'],
        ]);

        $user = $request->user();
        $user->modo_activo = $data['modo_activo'];
        $user->save();

        return response()->json($this->profilePayload($user->fresh()));
    }

    /**
     * Persist the onboarding wizard answers and mark onboarding complete.
     */
    public function onboarding(Request $request): JsonResponse
    {
        $data = $request->validate([
            'modo_activo' => ['required', 'in:bolsa,oposicion,docente'],
            'especialidades' => ['required', 'array', 'min:1'],
            'especialidades.*' => ['integer', 'exists:specialties,id'],
            'ccaa_id' => ['sometimes', 'nullable', 'integer', 'exists:ccaas,id'],
            'ccaa_preferidas' => ['sometimes', 'nullable', 'array'],
            'ccaa_preferidas.*' => ['integer'],
            'direccion_origen' => ['sometimes', 'nullable', 'string', 'max:300'],
            'lat_origen' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'lng_origen' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'nombre_gva' => ['sometimes', 'nullable', 'string', 'max:200'],
        ]);

        $user = $request->user();
        $anyo = (int) now()->year;

        DB::transaction(function () use ($user, $data, $anyo) {
            $user->modo_activo = $data['modo_activo'];
            if (array_key_exists('ccaa_id', $data)) {
                $user->ccaa_id = $data['ccaa_id'];
            }
            if (array_key_exists('ccaa_preferidas', $data)) {
                $user->ccaa_preferidas = $data['ccaa_preferidas'];
            }
            if (array_key_exists('direccion_origen', $data)) {
                $user->direccion_origen = $data['direccion_origen'];
            }
            if (array_key_exists('lat_origen', $data) && array_key_exists('lng_origen', $data)) {
                $user->lat_origen = $data['lat_origen'];
                $user->lng_origen = $data['lng_origen'];
            }
            if ($data['modo_activo'] === 'bolsa' && array_key_exists('nombre_gva', $data)) {
                $user->nombre_gva = $data['nombre_gva'];
            }
            $user->onboarding_completed = true;
            $user->save();

            foreach ($data['especialidades'] as $specialtyId) {
                UserEspecialidad::firstOrCreate([
                    'user_id' => $user->id,
                    'specialty_id' => $specialtyId,
                    'anyo' => $anyo,
                ]);
            }
        });

        return response()->json($this->profilePayload($user->fresh()));
    }

    /**
     * Add (or update) a specialty bolsa entry for the user.
     */
    public function storeEspecialidad(Request $request): JsonResponse
    {
        $data = $request->validate([
            'specialty_id' => ['required', 'integer', 'exists:specialties,id'],
            'posicion_bolsa' => ['nullable', 'integer', 'min:1'],
            'estado_bolsa' => ['nullable', 'string', 'max:20'],
            'anyo' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $especialidad = UserEspecialidad::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'specialty_id' => $data['specialty_id'],
                'anyo' => $data['anyo'],
            ],
            [
                'posicion_bolsa' => $data['posicion_bolsa'] ?? null,
                'estado_bolsa' => $data['estado_bolsa'] ?? null,
            ],
        );

        $especialidad->load('specialty');

        return response()->json([
            'specialty_id' => $especialidad->specialty_id,
            'specialty_name' => $especialidad->specialty?->name,
            'posicion_bolsa' => $especialidad->posicion_bolsa,
            'estado_bolsa' => $especialidad->estado_bolsa,
            'anyo' => $especialidad->anyo,
        ], 201);
    }

    /**
     * Remove every bolsa entry for a specialty from the user's profile.
     */
    public function destroyEspecialidad(Request $request, int $specialty): JsonResponse
    {
        $deleted = UserEspecialidad::where('user_id', $request->user()->id)
            ->where('specialty_id', $specialty)
            ->delete();

        return response()->json(['deleted' => $deleted]);
    }

    /**
     * Aggregated dashboard payload.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = Carbon::today();

        $procesosQuery = Proceso::query()
            ->with('colectivo')
            ->where('estado', '!=', 'cerrado')
            ->orderByRaw("CASE estado WHEN 'publicado' THEN 0 ELSE 1 END")
            ->orderBy('fecha_fin_peticiones');

        if ($user->ccaa_id) {
            $procesosQuery->where('ccaa_id', $user->ccaa_id);
        }

        $procesos = $procesosQuery->get();

        $procesosActivos = $procesos->map(fn (Proceso $p) => [
            'id' => $p->id,
            'nombre' => $p->nombre,
            'estado' => $p->estado,
            'fecha_publicacion_vacantes' => $p->fecha_publicacion_vacantes?->toDateString(),
            'fecha_fin_peticiones' => $p->fecha_fin_peticiones?->toDateString(),
            'dias_para_adjudicacion' => $p->fecha_adjudicacion
                ? (int) $today->diffInDays($p->fecha_adjudicacion, false)
                : null,
        ])->all();

        $misEspecialidades = $user->especialidades()->with('specialty')->get()
            ->map(fn (UserEspecialidad $e) => [
                'specialty_id' => $e->specialty_id,
                'specialty_name' => $e->specialty?->name,
                'posicion_bolsa' => $e->posicion_bolsa,
                'estado_bolsa' => $e->estado_bolsa,
                'anyo' => $e->anyo,
            ])->all();

        // Upcoming, dated milestones across the active procesos.
        $proximosPlazos = [];
        foreach ($procesos as $p) {
            foreach ([
                'inicio_peticiones' => $p->fecha_inicio_peticiones,
                'fin_peticiones' => $p->fecha_fin_peticiones,
                'adjudicacion' => $p->fecha_adjudicacion,
            ] as $tipo => $fecha) {
                if ($fecha && $fecha->gte($today)) {
                    $proximosPlazos[] = [
                        'proceso_id' => $p->id,
                        'proceso' => $p->nombre,
                        'tipo' => $tipo,
                        'fecha' => $fecha->toDateString(),
                    ];
                }
            }
        }
        usort($proximosPlazos, fn ($a, $b) => strcmp($a['fecha'], $b['fecha']));

        $historial = $user->historial()->with(['specialty', 'centroAdjudicado', 'proceso'])
            ->orderByDesc('anyo')->get();
        $ultimo = $historial->first();

        $resumenHistorial = [
            'cursos_trabajados' => $historial->pluck('anyo')->unique()->count(),
            'ultimo_centro' => $ultimo?->centroAdjudicado?->nombre,
            'ultima_posicion' => $ultimo?->posicion_definitiva,
        ];

        $historialDetallado = $historial->map(fn (\App\Models\UserHistorial $h) => [
            'id' => $h->id,
            'anyo' => $h->anyo,
            'curso' => $h->proceso?->curso ?? ($h->anyo ? $h->anyo.'-'.($h->anyo + 1) : null),
            'especialidad' => $h->specialty?->name,
            'proceso' => $h->proceso?->nombre,
            'estado' => $h->estado,
            'posicion_provisional' => $h->posicion_provisional,
            'posicion_definitiva' => $h->posicion_definitiva,
            'centro' => $h->centroAdjudicado?->nombre,
            'centro_codigo' => $h->centroAdjudicado?->codigo,
            'localidad' => $h->centroAdjudicado?->localidad,
            'lloc' => $h->lloc_adjudicado,
            'jornada' => $h->jornada_adjudicada,
            'fecha_adjudicacion' => $h->fecha_adjudicacion?->toDateString(),
        ])->all();

        $info = [
            'name' => $user->name,
            'email' => $user->email,
            'nombre_gva' => $user->nombre_gva,
            'colectivo' => $user->colectivo?->name,
            'cuerpo' => $user->colectivo?->body,
            'ccaa' => $user->ccaa?->name,
            'direccion_origen' => $user->direccion_origen,
            'num_especialidades' => count($misEspecialidades),
            'miembro_desde' => $user->created_at?->toDateString(),
        ];

        // The most recent participant listing imported (scoped to the user's
        // CCAA): the official position must be read from THIS listing, and the
        // UI shows its date.
        $procesoListado = $this->latestParticipantListing($user);

        // Recent listing changes (vacancies + participants) with the date of the
        // last update, so the dashboard can surface "qué ha cambiado y cuándo".
        $actualizaciones = $this->recentUpdates($user);

        return response()->json([
            'procesos_activos' => $procesosActivos,
            'mis_especialidades' => $misEspecialidades,
            // No per-user vacancy favourites model yet; placeholder for the UI.
            'mis_vacantes_favoritas' => [],
            'proximos_plazos' => $proximosPlazos,
            'resumen_historial' => $resumenHistorial,
            'historial' => $historialDetallado,
            'info' => $info,
            'proceso_listado' => $procesoListado,
            'actualizaciones' => $actualizaciones,
        ]);
    }

    /**
     * The authenticated user's weekly continuous-adjudication history, matched
     * Aggregate "where am I in the lists?" view: searches every imported
     * participant listing (across all procesos) and the weekly continuous
     * adjudications by the user's nombre_gva, returning where they appear and
     * at what position.
     */
    public function misListados(Request $request): JsonResponse
    {
        $user = $request->user();

        // A free-text search (?q=) lets you look up ANY name in the lists, not
        // only your own. With no query we default to the user's own nombre_gva.
        $q = trim((string) $request->query('q', ''));
        $isSearch = $q !== '';
        $term = $isSearch ? $q : (string) $user->nombre_gva;

        // Not configured AND not searching → prompt to set the GVA name. The
        // search box still works without it (handled client-side).
        if ($term === '') {
            return response()->json([
                'configured' => false,
                'is_search' => false,
                'query' => '',
                'nombre_gva' => $user->nombre_gva,
                'message' => 'Configura tu nombre GVA en el perfil para localizarte en las listas, o busca cualquier nombre arriba.',
                'resultados' => [],
            ]);
        }

        $needle = mb_strtolower($term);

        // Exact match for the user's own name; partial (LIKE) for free searches
        // so a surname or partial name finds people.
        $matchName = function ($query, string $column = 'nombre_gva') use ($isSearch, $needle) {
            return $isSearch
                ? $query->whereRaw('LOWER('.$column.') LIKE ?', ['%'.$this->escapeLike($needle).'%'])
                : $query->whereRaw('LOWER('.$column.') = ?', [$needle]);
        };

        $userCodes = $user->especialidades()->with('specialty')->get()
            ->flatMap(fn (UserEspecialidad $e) => [$e->specialty?->codigo, $e->specialty?->code])
            ->filter()->map(fn ($c) => (string) $c)->all();

        // All participant-list matches across every proceso, grouped by person.
        $rows = \App\Models\ParticipanteProceso::query()
            ->where(fn ($query) => $matchName($query))
            ->with('proceso:id,nombre')
            ->orderBy('nombre_gva')
            ->limit(3000) // safety cap for very broad searches
            ->get();

        // Continua matches, also grouped by person.
        $continuasRows = \App\Models\AdjudicacionContinua::query()
            ->where(function ($query) use ($matchName, $isSearch, $user) {
                $matchName($query);
                if (! $isSearch) {
                    $query->orWhere('user_id', $user->id);
                }
            })
            ->orderByDesc('fecha')->limit(2000)->get();

        // Pre-load the latest listing date per proceso once.
        $procesoIds = $rows->pluck('proceso_id')->unique()->all();
        $fechas = \App\Models\ParticipanteImportacion::query()
            ->whereIn('proceso_id', $procesoIds)
            ->orderByDesc('importado_en')->get()
            ->groupBy('proceso_id')
            ->map(fn ($g) => $g->first()?->importado_en?->toDateString());

        $personasParticipantes = $rows->groupBy(fn ($p) => trim((string) $p->nombre_gva));
        $personasContinuas = $continuasRows->groupBy(fn ($a) => trim((string) ($a->nombre_gva ?? '')));

        // Union of names found in either source, capped.
        $maxPersonas = 30;
        $nombres = collect($personasParticipantes->keys())
            ->merge($personasContinuas->keys())
            ->filter(fn ($n) => $n !== '')
            ->unique()->sort()->values();
        $truncated = $nombres->count() > $maxPersonas;
        $nombres = $nombres->take($maxPersonas);

        $resultados = [];
        foreach ($nombres as $nombre) {
            $resultados[] = [
                'nombre_gva' => $nombre,
                'procesos' => $this->buildProcesoCards($personasParticipantes->get($nombre, collect()), $userCodes, $fechas),
                'continuas' => collect($personasContinuas->get($nombre, collect()))
                    ->map(fn ($a) => [
                        'fecha' => $a->fecha?->toDateString(),
                        'cuerpo' => $a->cuerpo,
                        'estado' => $a->estado,
                        'especialidad_codigo' => $a->especialidad_codigo,
                        'posicion' => $a->posicion,
                        'centro' => $a->centro_nombre,
                        'localitat' => $a->localitat,
                    ])->values()->all(),
            ];
        }

        return response()->json([
            'configured' => (bool) $user->nombre_gva,
            'is_search' => $isSearch,
            'query' => $term,
            'nombre_gva' => $user->nombre_gva,
            'total_personas' => $truncated ? $maxPersonas : count($resultados),
            'truncated' => $truncated,
            'resultados' => $resultados,
        ]);
    }

    /**
     * Build the per-proceso position cards for a single person's participant rows.
     *
     * @param  \Illuminate\Support\Collection  $personRows
     * @param  array<int, string>  $userCodes
     * @param  \Illuminate\Support\Collection  $fechas  proceso_id => listado date
     * @return array<int, array<string, mixed>>
     */
    private function buildProcesoCards($personRows, array $userCodes, $fechas): array
    {
        $procesos = [];
        foreach (collect($personRows)->groupBy('proceso_id') as $procesoId => $rows) {
            $proceso = $rows->first()->proceso;
            if (! $proceso) {
                continue;
            }
            // Prefer the row matching one of the user's own specialty codes.
            $row = $rows->first(fn ($p) => in_array((string) $p->especialidad_codigo, $userCodes, true)) ?? $rows->first();

            $procesos[] = [
                'proceso_id' => $proceso->id,
                'proceso' => $proceso->nombre,
                'posicion' => $row->posicion,
                'estado' => $row->estado,
                'cambio' => $row->cambio,
                'especialidad_codigo' => $row->especialidad_codigo,
                'listado_fecha' => $fechas[$procesoId] ?? null,
                'adjudicacion' => $row->estado === 'Adjudicat' ? [
                    'lloc' => $row->lloc_adjudicado,
                    'centro_nombre' => $row->centro_nombre,
                    'localitat' => $row->localitat,
                    'jornada' => $row->jornada,
                ] : null,
                // When the person appears in several specialties of the same proceso.
                'otras' => $rows->count() > 1
                    ? $rows->map(fn ($p) => [
                        'especialidad_codigo' => $p->especialidad_codigo,
                        'posicion' => $p->posicion,
                        'estado' => $p->estado,
                    ])->values()->all()
                    : [],
            ];
        }

        return $procesos;
    }

    /** Escape LIKE wildcards in a user-supplied search term. */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * by nombre_gva (or a previously linked user_id), newest tanda first.
     */
    public function adjudicacionesContinuas(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->nombre_gva) {
            return response()->json([
                'found' => false,
                'message' => 'Configura tu nombre GVA en el perfil para ver tus adjudicaciones semanales.',
                'data' => [],
            ]);
        }

        $rows = \App\Models\AdjudicacionContinua::query()
            ->where(fn ($q) => $q->where('user_id', $user->id)
                ->orWhereRaw('LOWER(nombre_gva) = ?', [mb_strtolower($user->nombre_gva)]))
            ->orderByDesc('fecha')
            ->limit(40)
            ->get()
            ->map(fn ($a) => [
                'fecha' => $a->fecha?->toDateString(),
                'cuerpo' => $a->cuerpo,
                'estado' => $a->estado,
                'especialidad_codigo' => $a->especialidad_codigo,
                'posicion' => $a->posicion,
                'centro' => $a->centro_nombre,
                'localidad' => $a->localitat,
                'lloc' => $a->lloc_adjudicado,
                'jornada' => $a->jornada,
            ]);

        return response()->json(['found' => $rows->isNotEmpty(), 'data' => $rows]);
    }

    /**
     * The proceso whose participant list was imported most recently (scoped to
     * the user's CCAA when set), with the import date. Used so the official
     * position is always read from the latest available listing.
     *
     * @return array{id:int,nombre:string,fecha:string|null}|null
     */
    private function latestParticipantListing(User $user): ?array
    {
        $import = \App\Models\ParticipanteImportacion::query()
            ->with('proceso:id,nombre,ccaa_id')
            ->when($user->ccaa_id, fn ($q) => $q->whereHas('proceso', fn ($p) => $p->where('ccaa_id', $user->ccaa_id)))
            ->orderByDesc('importado_en')
            ->first();

        if (! $import || ! $import->proceso) {
            return null;
        }

        return [
            'id' => $import->proceso->id,
            'nombre' => $import->proceso->nombre,
            'fecha' => $import->importado_en?->toDateString(),
        ];
    }

    /**
     * Recent listing updates (vacancies + participants) that introduced changes,
     * scoped to the user's CCAA, newest first, plus the overall last-update date.
     *
     * @return array{ultima_actualizacion:string|null, items:array<int,array<string,mixed>>}
     */
    private function recentUpdates(User $user): array
    {
        $scope = fn ($q) => $user->ccaa_id
            ? $q->whereHas('proceso', fn ($p) => $p->where('ccaa_id', $user->ccaa_id))
            : $q;

        $vacantes = \App\Models\ProcesoImportacion::with('proceso:id,nombre')
            ->where('es_primera', false)
            ->where(fn ($q) => $q->where('nuevas', '>', 0)->orWhere('modificadas', '>', 0)->orWhere('eliminadas', '>', 0))
            ->tap($scope)
            ->orderByDesc('importado_en')
            ->limit(10)
            ->get()
            ->map(fn ($i) => [
                'tipo' => 'vacantes',
                'proceso' => $i->proceso?->nombre,
                'fecha' => $i->importado_en?->toIso8601String(),
                'nuevas' => $i->nuevas,
                'modificadas' => $i->modificadas,
                'eliminadas' => $i->eliminadas,
            ]);

        $participantes = \App\Models\ParticipanteImportacion::with('proceso:id,nombre')
            ->where('es_primera', false)
            ->where(fn ($q) => $q->where('nuevos', '>', 0)->orWhere('modificados', '>', 0)->orWhere('eliminados', '>', 0))
            ->tap($scope)
            ->orderByDesc('importado_en')
            ->limit(10)
            ->get()
            ->map(fn ($i) => [
                'tipo' => 'participantes',
                'proceso' => $i->proceso?->nombre,
                'fecha' => $i->importado_en?->toIso8601String(),
                'nuevas' => $i->nuevos,
                'modificadas' => $i->modificados,
                'eliminadas' => $i->eliminados,
            ]);

        $items = $vacantes->concat($participantes)
            ->sortByDesc('fecha')
            ->take(8)
            ->values()
            ->all();

        return [
            'ultima_actualizacion' => $items[0]['fecha'] ?? null,
            'items' => $items,
        ];
    }

    /**
     * The authenticated user's ordered (selected) vacancy list.
     */
    public function lista(Request $request): JsonResponse
    {
        $data = $request->validate([
            'specialty_id' => ['sometimes', 'nullable', 'integer', 'exists:specialties,id'],
            'proceso_id' => ['sometimes', 'nullable', 'integer', 'exists:procesos,id'],
        ]);

        $user = $request->user();
        $procesoId = $data['proceso_id'] ?? $this->defaultProcesoId($user);

        $lists = UserList::query()
            ->where('user_id', $user->id)
            ->when($procesoId, fn ($q) => $q->where('proceso_id', $procesoId))
            ->when(! empty($data['specialty_id']), fn ($q) => $q->where('specialty_id', $data['specialty_id']))
            ->pluck('id');

        $items = [];
        if ($lists->isNotEmpty()) {
            $prefs = \App\Models\UserVacancyPreference::query()
                ->with('vacancy')
                ->whereIn('user_list_id', $lists)
                ->where('status', 'selected')
                ->orderBy('position')
                ->get();

            $items = $prefs->filter(fn ($p) => $p->vacancy)->map(function ($p) {
                return array_merge(
                    (new VacancyResource($p->vacancy))->toArray(request()),
                    ['notes' => $p->notes, 'position' => $p->position, 'status' => $p->status],
                );
            })->values()->all();
        }

        return response()->json([
            'proceso_id' => $procesoId,
            'items' => $items,
        ]);
    }

    /**
     * Persist the full ordered list coming from the SPA for the logged-in user.
     */
    public function syncLista(Request $request): JsonResponse
    {
        $data = $request->validate([
            'specialty_id' => ['required', 'integer', 'exists:specialties,id'],
            'proceso_id' => ['sometimes', 'nullable', 'integer', 'exists:procesos,id'],
            'items' => ['present', 'array'],
            'items.*.vacancy_id' => ['required', 'integer', 'exists:vacancies,id'],
            'items.*.position' => ['nullable', 'integer'],
            'items.*.status' => ['nullable', 'in:selected,discarded,neutral,revisar'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $list = $this->findOrCreateUserList($user, $data['specialty_id'], $data['proceso_id'] ?? null);

        DB::transaction(function () use ($list, $data) {
            // Replace this list's preferences with the incoming ordered set.
            $list->preferences()->delete();

            $rows = [];
            foreach ($data['items'] as $i => $item) {
                $rows[] = [
                    'user_list_id' => $list->id,
                    'vacancy_id' => $item['vacancy_id'],
                    'position' => $item['position'] ?? ($i + 1),
                    'status' => $item['status'] ?? 'selected',
                    'notes' => $item['notes'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('user_vacancy_preferences')->insert($chunk);
            }
        });

        return response()->json([
            'list_id' => $list->id,
            'proceso_id' => $list->proceso_id,
            'saved' => count($data['items']),
        ]);
    }

    private function findOrCreateUserList(User $user, int $specialtyId, ?int $procesoId): UserList
    {
        // Synthetic session_token keeps the existing NOT NULL + unique
        // (session_token, specialty_id) constraint satisfied for auth lists.
        $token = 'auth-'.$user->id.'-'.($procesoId ?? 0).'-'.$specialtyId;

        return UserList::firstOrCreate(
            [
                'user_id' => $user->id,
                'proceso_id' => $procesoId,
                'specialty_id' => $specialtyId,
            ],
            ['session_token' => $token],
        );
    }

    private function defaultProcesoId(User $user): ?int
    {
        return Proceso::query()
            ->where('estado', 'publicado')
            ->when($user->ccaa_id, fn ($q) => $q->where('ccaa_id', $user->ccaa_id))
            ->when($user->colectivo_id, fn ($q) => $q->where('colectivo_id', $user->colectivo_id))
            ->orderByDesc('anyo')
            ->value('id');
    }

    /**
     * Resolve the current request's impersonation state from the cache. When a
     * super-admin is impersonating, the impersonation token id is stored under
     * "impersonation:{tokenId}" with the original admin's name.
     *
     * @return array{active:bool, by:?string}
     */
    private function impersonationState(): array
    {
        $tokenId = request()->user()?->currentAccessToken()?->id;

        if ($tokenId) {
            $payload = \Illuminate\Support\Facades\Cache::get('impersonation:'.$tokenId);
            if ($payload) {
                return ['active' => true, 'by' => $payload['admin_name'] ?? 'Administrador'];
            }
        }

        return ['active' => false, 'by' => null];
    }

    /**
     * Build the canonical profile array used by show()/update().
     */
    private function profilePayload(User $user): array
    {
        $user->load(['ccaa', 'colectivo', 'especialidades.specialty', 'historial.specialty', 'historial.centroAdjudicado']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'nombre_gva' => $user->nombre_gva,
            'avatar_url' => $user->avatar_url,
            'locale' => $user->locale,
            'ccaa' => $user->ccaa,
            'colectivo' => $user->colectivo,
            'direccion_origen' => $user->direccion_origen,
            'lat_origen' => $user->lat_origen,
            'lng_origen' => $user->lng_origen,
            'notificaciones_email' => (bool) $user->notificaciones_email,
            // SaaS state consumed by the SPA (sidebar, gating, banners).
            'role' => $user->role,
            'modo_activo' => $user->modo_activo,
            'ccaa_preferidas' => $user->ccaa_preferidas,
            'onboarding_completed' => (bool) $user->onboarding_completed,
            'plan' => $user->plan,
            'plan_status' => $user->plan_status,
            'plan_label' => $user->planLabel(),
            'plan_status_label' => $user->planStatusLabel(),
            'plan_expires_at' => $user->plan_expires_at?->toIso8601String(),
            'is_paid' => $user->isPaid(),
            'is_admin' => $user->isAdmin(),
            'is_superadmin' => $user->isSuperAdmin(),
            'features' => app(\App\Policies\FeaturePolicy::class)->featureMap($user),
            'is_impersonated' => $this->impersonationState()['active'],
            'impersonated_by' => $this->impersonationState()['by'],
            'especialidades' => $user->especialidades->map(fn (UserEspecialidad $e) => [
                'specialty_id' => $e->specialty_id,
                'specialty_name' => $e->specialty?->name,
                'posicion_bolsa' => $e->posicion_bolsa,
                'estado_bolsa' => $e->estado_bolsa,
                'anyo' => $e->anyo,
            ])->all(),
            'historial' => $user->historial->map(fn ($h) => [
                'anyo' => $h->anyo,
                'specialty_name' => $h->specialty?->name,
                'posicion_definitiva' => $h->posicion_definitiva,
                'estado' => $h->estado,
                'centro_nombre' => $h->centroAdjudicado?->nombre,
                'localitat' => $h->centroAdjudicado?->localidad,
            ])->all(),
        ];
    }

    /**
     * Geocode the home address and persist the coordinates on the user.
     */
    private function geocodeHomeAddress(User $user, ?string $address): void
    {
        if (! $address || ! $this->maps->isConfigured()) {
            $user->lat_origen = null;
            $user->lng_origen = null;

            return;
        }

        try {
            $result = $this->maps->geocode($address);
        } catch (RuntimeException $e) {
            $result = null;
        }

        $user->lat_origen = $result['lat'] ?? null;
        $user->lng_origen = $result['lng'] ?? null;
    }

    /**
     * Dashboard hero data: greeting, mode-card stats, upcoming adjudicación countdown.
     */
    public function hero(Request $request): JsonResponse
    {
        $user = $request->user();
        $now = Carbon::now();

        // Time-of-day greeting
        $hour = (int) $now->format('G');
        if ($hour >= 6 && $hour < 14) {
            $greeting = 'Buenos días';
        } elseif ($hour >= 14 && $hour < 21) {
            $greeting = 'Buenas tardes';
        } else {
            $greeting = 'Buenas noches';
        }

        // First-name from nombre_gva or user name
        $rawName = $user->nombre_gva ?: $user->name ?: '';
        $nombre = explode(' ', trim($rawName))[0] ?? '';

        // Spanish date: e.g. "lunes, 29 de junio de 2026"
        $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
                  'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
        $fechaTexto = sprintf(
            '%s, %d de %s de %d',
            $dias[$now->dayOfWeek],
            $now->day,
            $meses[$now->month],
            $now->year
        );

        // Bolsa stats
        $especialidades = UserEspecialidad::where('user_id', $user->id)->get();
        $posicionMejor = $especialidades->whereNotNull('posicion_bolsa')->min('posicion_bolsa');
        $estadoMejor = $posicionMejor !== null
            ? $especialidades->where('posicion_bolsa', $posicionMejor)->first()?->estado_bolsa
            : null;

        $bolsaStats = [
            'total_especialidades' => $especialidades->count() ?: null,
            'posicion_mejor' => $posicionMejor,
            'estado_mejor' => $estadoMejor,
        ];

        // Oposición stats
        $temaStats = OposicionTema::where('user_id', $user->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $oposicionStats = [
            'temas_total' => $temaStats->sum(),
            'temas_dominados' => (int) ($temaStats['dominado'] ?? 0),
            'temas_progreso' => (int) ($temaStats['en_progreso'] ?? 0),
        ];

        // Next upcoming academic calendar event
        $nextEvent = AcademicCalendarEvent::where('event_date', '>=', $now->toDateString())
            ->where('visibility', '!=', 'superadmin_only')
            ->orderBy('event_date')
            ->first();

        $adjudicacionProxima = null;
        if ($nextEvent) {
            $eventDate = Carbon::parse($nextEvent->event_date);
            $diasRestantes = (int) $now->startOfDay()->diffInDays($eventDate, false);
            $adjudicacionProxima = [
                'fecha' => $nextEvent->event_date,
                'dias_restantes' => max(0, $diasRestantes),
                'titulo' => $nextEvent->title,
                'tipo' => $nextEvent->event_type,
            ];
        }

        return response()->json([
            'greeting' => $greeting,
            'nombre' => $nombre,
            'fecha_texto' => $fechaTexto,
            'stats' => [
                'bolsa' => $bolsaStats,
                'oposicion' => $oposicionStats,
            ],
            'adjudicacion_proxima' => $adjudicacionProxima,
        ]);
    }
}
