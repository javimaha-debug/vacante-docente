<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\VacancyResource;
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

        return response()->json($user);
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

        $procesosActivos = $procesosQuery->get()->map(fn (Proceso $p) => [
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
        foreach ($procesosQuery->get() as $p) {
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

        $historial = $user->historial()->with(['specialty', 'centroAdjudicado'])
            ->orderByDesc('anyo')->get();
        $ultimo = $historial->first();

        $resumenHistorial = [
            'cursos_trabajados' => $historial->pluck('anyo')->unique()->count(),
            'ultimo_centro' => $ultimo?->centroAdjudicado?->nombre,
            'ultima_posicion' => $ultimo?->posicion_definitiva,
        ];

        return response()->json([
            'procesos_activos' => $procesosActivos,
            'mis_especialidades' => $misEspecialidades,
            // No per-user vacancy favourites model yet; placeholder for the UI.
            'mis_vacantes_favoritas' => [],
            'proximos_plazos' => $proximosPlazos,
            'resumen_historial' => $resumenHistorial,
        ]);
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
            'items.*.status' => ['nullable', 'in:selected,discarded,neutral'],
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
}
