<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OposicionEspecialidad;
use App\Models\OposicionSesion;
use App\Models\OposicionTema;
use App\Models\TemaOficial;
use App\Models\TemarioOficial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OposicionPreparacionController extends Controller
{
    /* ---------------------------------------------------------------- */
    /* Especialidades */
    /* ---------------------------------------------------------------- */

    /**
     * The specialties the user is preparing.
     */
    public function especialidades(Request $request): JsonResponse
    {
        $rows = OposicionEspecialidad::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at')
            ->get();

        return response()->json(['data' => $rows->map(fn ($e) => $this->especialidadArray($e))]);
    }

    /**
     * Add a specialty the user is preparing.
     */
    public function storeEspecialidad(Request $request): JsonResponse
    {
        $data = $request->validate([
            'especialidad_code' => ['required', 'string', 'max:50'],
            'cuerpo' => ['required', 'in:maestros,secundaria,fp,otros'],
            'comunidad_autonoma' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $especialidad = OposicionEspecialidad::firstOrCreate(
            [
                'user_id' => $request->user()->id,
                'especialidad_code' => $data['especialidad_code'],
                'cuerpo' => $data['cuerpo'],
            ],
            [
                'comunidad_autonoma' => $data['comunidad_autonoma'] ?? 'valenciana',
            ],
        );

        return response()->json($this->especialidadArray($especialidad), 201);
    }

    /**
     * Remove a specialty (owner only).
     */
    public function destroyEspecialidad(Request $request, OposicionEspecialidad $especialidad): JsonResponse
    {
        if ($especialidad->user_id !== $request->user()->id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $especialidad->delete();

        return response()->json(['deleted' => true]);
    }

    /* ---------------------------------------------------------------- */
    /* Temas */
    /* ---------------------------------------------------------------- */

    /**
     * List temas, optionally filtered by especialidad / status.
     */
    public function temas(Request $request): JsonResponse
    {
        $data = $request->validate([
            'especialidad' => ['sometimes', 'nullable', 'string', 'max:50'],
            'status' => ['sometimes', 'nullable', 'in:pendiente,en_progreso,dominado'],
        ]);

        $temas = OposicionTema::query()
            ->where('user_id', $request->user()->id)
            ->when($data['especialidad'] ?? null, fn ($q, $c) => $q->where('especialidad_code', $c))
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('numero')
            ->get();

        return response()->json(['data' => $temas->map(fn ($t) => $this->temaArray($t))]);
    }

    /**
     * Create a tema.
     */
    public function storeTema(Request $request): JsonResponse
    {
        $data = $request->validate([
            'especialidad_code' => ['required', 'string', 'max:50'],
            'numero' => ['required', 'integer', 'min:0'],
            'titulo' => ['required', 'string', 'max:300'],
            'status' => ['sometimes', 'in:pendiente,en_progreso,dominado'],
            'notas' => ['sometimes', 'nullable', 'string'],
        ]);

        $tema = OposicionTema::create([
            'user_id' => $request->user()->id,
            'especialidad_code' => $data['especialidad_code'],
            'numero' => $data['numero'],
            'titulo' => $data['titulo'],
            'status' => $data['status'] ?? 'pendiente',
            'notas' => $data['notas'] ?? null,
        ]);

        return response()->json($this->temaArray($tema), 201);
    }

    /**
     * Update a tema's status and/or notes (owner only).
     */
    public function updateTema(Request $request, OposicionTema $tema): JsonResponse
    {
        if ($tema->user_id !== $request->user()->id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $data = $request->validate([
            'status' => ['sometimes', 'in:pendiente,en_progreso,dominado'],
            'notas' => ['sometimes', 'nullable', 'string'],
            'titulo' => ['sometimes', 'string', 'max:300'],
            'numero' => ['sometimes', 'integer', 'min:0'],
            'esquema_progreso' => ['sometimes', 'nullable', 'array'],
            'esquema_progreso.*' => ['integer'],
        ]);

        // Stamp the study time whenever the tema moves forward.
        if (array_key_exists('status', $data) && $data['status'] !== $tema->status) {
            $tema->last_studied_at = now();
        }

        $tema->fill($data)->save();

        return response()->json($this->temaArray($tema));
    }

    /**
     * Delete a tema (owner only).
     */
    public function destroyTema(Request $request, OposicionTema $tema): JsonResponse
    {
        if ($tema->user_id !== $request->user()->id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $tema->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * Bulk-create temas (import a temario list).
     */
    public function bulkTemas(Request $request): JsonResponse
    {
        $data = $request->validate([
            'especialidad_code' => ['required', 'string', 'max:50'],
            'temas' => ['required', 'array', 'min:1'],
            'temas.*.numero' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'temas.*.titulo' => ['required', 'string', 'max:300'],
        ]);

        $user = $request->user();
        $now = now();

        // Continue numbering after the highest existing tema for this specialty.
        $maxNumero = (int) OposicionTema::query()
            ->where('user_id', $user->id)
            ->where('especialidad_code', $data['especialidad_code'])
            ->max('numero');

        $created = DB::transaction(function () use ($data, $user, $now, &$maxNumero) {
            $rows = [];
            foreach ($data['temas'] as $i => $t) {
                $numero = $t['numero'] ?? null;
                if ($numero === null) {
                    $numero = ++$maxNumero;
                }
                $rows[] = OposicionTema::create([
                    'user_id' => $user->id,
                    'especialidad_code' => $data['especialidad_code'],
                    'numero' => $numero,
                    'titulo' => $t['titulo'],
                    'status' => 'pendiente',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return $rows;
        });

        return response()->json([
            'created' => count($created),
            'data' => collect($created)->map(fn ($t) => $this->temaArray($t)),
        ], 201);
    }

    /* ---------------------------------------------------------------- */
    /* Temario oficial (BOE) */
    /* ---------------------------------------------------------------- */

    /**
     * Whether an official temario exists for a specialty + cuerpo, with a preview.
     */
    public function temarioOficial(Request $request): JsonResponse
    {
        $data = $request->validate([
            'especialidad_code' => ['required', 'string', 'max:50'],
            'cuerpo' => ['sometimes', 'nullable', 'in:maestros,secundaria,fp,otros'],
        ]);

        $temario = TemarioOficial::query()
            ->where('especialidad_code', $data['especialidad_code'])
            ->when($data['cuerpo'] ?? null, fn ($q, $c) => $q->where('cuerpo', $c))
            ->first();

        if (! $temario) {
            return response()->json(['exists' => false]);
        }

        $preview = $temario->temas()->orderBy('numero')->limit(5)->get(['numero', 'titulo']);

        return response()->json([
            'exists' => true,
            'temario_id' => $temario->id,
            'especialidad_nombre' => $temario->especialidad_nombre,
            'cuerpo' => $temario->cuerpo,
            'source_order' => $temario->source_order,
            'total_temas' => $temario->total_temas,
            'preview' => $preview,
            // Whether the user already has temas for this specialty.
            'ya_importado' => OposicionTema::where('user_id', $request->user()->id)
                ->where('especialidad_code', $data['especialidad_code'])->exists(),
        ]);
    }

    /**
     * Copy the official temario into the user's oposicion_temas as a starting point.
     */
    public function importOficial(Request $request): JsonResponse
    {
        $data = $request->validate([
            'especialidad_code' => ['required', 'string', 'max:50'],
            'cuerpo' => ['sometimes', 'nullable', 'in:maestros,secundaria,fp,otros'],
        ]);

        $user = $request->user();

        $temario = TemarioOficial::query()
            ->where('especialidad_code', $data['especialidad_code'])
            ->when($data['cuerpo'] ?? null, fn ($q, $c) => $q->where('cuerpo', $c))
            ->with('temas')
            ->first();

        if (! $temario) {
            return response()->json(['message' => 'No hay temario oficial para esta especialidad.'], 404);
        }

        $created = DB::transaction(function () use ($temario, $user, $data) {
            // Don't duplicate temas the user already has for this specialty.
            $existing = OposicionTema::where('user_id', $user->id)
                ->where('especialidad_code', $data['especialidad_code'])
                ->pluck('numero')->all();

            $rows = 0;
            foreach ($temario->temas as $tema) {
                if (in_array($tema->numero, $existing, true)) {
                    continue;
                }
                OposicionTema::create([
                    'user_id' => $user->id,
                    'especialidad_code' => $data['especialidad_code'],
                    'numero' => $tema->numero,
                    'titulo' => $tema->titulo,
                    'status' => 'pendiente',
                    'es_oficial' => true,
                    'tema_oficial_id' => $tema->id,
                ]);
                $rows++;
            }

            return $rows;
        });

        return response()->json(['imported' => $created], 201);
    }

    /**
     * The official esquema + bibliography behind one of the user's temas.
     */
    public function temaOficialDetail(Request $request, OposicionTema $tema): JsonResponse
    {
        if ($tema->user_id !== $request->user()->id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $oficial = $tema->tema_oficial_id ? TemaOficial::find($tema->tema_oficial_id) : null;
        if (! $oficial) {
            return response()->json(['esquema' => null, 'bibliografia' => null, 'keywords' => null]);
        }

        return response()->json([
            'numero' => $oficial->numero,
            'titulo' => $oficial->titulo,
            'esquema' => $oficial->esquema,
            'bibliografia' => $oficial->bibliografia,
            'keywords' => $oficial->keywords,
            'tiempo_estimado_minutos' => $oficial->tiempo_estimado_minutos,
        ]);
    }

    /* ---------------------------------------------------------------- */
    /* Sesiones */
    /* ---------------------------------------------------------------- */

    /**
     * List study sessions (newest first).
     */
    public function sesiones(Request $request): JsonResponse
    {
        $sesiones = OposicionSesion::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(365)
            ->get();

        return response()->json(['data' => $sesiones->map(fn ($s) => $this->sesionArray($s))]);
    }

    /**
     * Log a study session. Touches last_studied_at on the worked temas.
     */
    public function storeSesion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fecha' => ['sometimes', 'nullable', 'date'],
            'minutos' => ['required', 'integer', 'min:1', 'max:1440'],
            'temas_estudiados' => ['sometimes', 'nullable', 'array'],
            'temas_estudiados.*' => ['integer'],
            'notas' => ['sometimes', 'nullable', 'string'],
        ]);

        $user = $request->user();
        $fecha = $data['fecha'] ?? now()->toDateString();
        $temaIds = $data['temas_estudiados'] ?? [];

        $sesion = OposicionSesion::create([
            'user_id' => $user->id,
            'fecha' => $fecha,
            'minutos' => $data['minutos'],
            'temas_estudiados' => $temaIds,
            'notas' => $data['notas'] ?? null,
        ]);

        // Stamp the worked temas (owned by the user) as recently studied.
        if (! empty($temaIds)) {
            OposicionTema::query()
                ->where('user_id', $user->id)
                ->whereIn('id', $temaIds)
                ->update(['last_studied_at' => now()]);
        }

        return response()->json($this->sesionArray($sesion), 201);
    }

    /* ---------------------------------------------------------------- */
    /* Stats */
    /* ---------------------------------------------------------------- */

    /**
     * Study statistics: total minutes, temas by status, sessions over the last
     * 30 days, % dominado and the current study streak (in days).
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        $totalMinutos = (int) OposicionSesion::where('user_id', $user->id)->sum('minutos');

        $porStatus = OposicionTema::query()
            ->where('user_id', $user->id)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $temasByStatus = [
            'pendiente' => (int) ($porStatus['pendiente'] ?? 0),
            'en_progreso' => (int) ($porStatus['en_progreso'] ?? 0),
            'dominado' => (int) ($porStatus['dominado'] ?? 0),
        ];
        $totalTemas = array_sum($temasByStatus);
        $pctDominado = $totalTemas > 0
            ? (int) round(($temasByStatus['dominado'] / $totalTemas) * 100)
            : 0;

        // Sessions over the last 30 days, grouped by day (for the mini-calendar).
        $desde = Carbon::today()->subDays(29);
        $sesiones30 = OposicionSesion::query()
            ->where('user_id', $user->id)
            ->where('fecha', '>=', $desde->toDateString())
            ->get();

        $porDia = $sesiones30->groupBy(fn ($s) => $s->fecha?->toDateString())
            ->map(fn ($g) => [
                'fecha' => $g->first()->fecha?->toDateString(),
                'minutos' => (int) $g->sum('minutos'),
                'sesiones' => $g->count(),
            ])->values();

        // Distinct study days (most recent first) for the streak calculation.
        $diasEstudio = OposicionSesion::query()
            ->where('user_id', $user->id)
            ->orderByDesc('fecha')
            ->pluck('fecha')
            ->map(fn ($f) => Carbon::parse($f)->toDateString())
            ->unique()
            ->values();

        return response()->json([
            'total_minutos' => $totalMinutos,
            'total_temas' => $totalTemas,
            'temas_by_status' => $temasByStatus,
            'pct_dominado' => $pctDominado,
            'sesiones_30_dias' => $porDia,
            'minutos_30_dias' => (int) $sesiones30->sum('minutos'),
            'racha_dias' => $this->streak($diasEstudio),
        ]);
    }

    /**
     * Current streak: consecutive study days ending today or yesterday.
     *
     * @param  Collection<int, string>  $dias  distinct study dates, newest first
     */
    private function streak($dias): int
    {
        if ($dias->isEmpty()) {
            return 0;
        }

        $today = Carbon::today();
        $first = Carbon::parse($dias->first());

        // The streak is only "live" if the latest study day is today or yesterday.
        if ($first->diffInDays($today) > 1) {
            return 0;
        }

        $streak = 1;
        for ($i = 1; $i < $dias->count(); $i++) {
            $prev = Carbon::parse($dias[$i - 1]);
            $curr = Carbon::parse($dias[$i]);
            if ($prev->copy()->subDay()->isSameDay($curr)) {
                $streak++;
            } else {
                break;
            }
        }

        return $streak;
    }

    /* ---------------------------------------------------------------- */
    /* Serializers */
    /* ---------------------------------------------------------------- */

    /** @return array<string, mixed> */
    private function especialidadArray(OposicionEspecialidad $e): array
    {
        return [
            'id' => $e->id,
            'especialidad_code' => $e->especialidad_code,
            'cuerpo' => $e->cuerpo,
            'comunidad_autonoma' => $e->comunidad_autonoma,
            'created_at' => $e->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function temaArray(OposicionTema $t): array
    {
        return [
            'id' => $t->id,
            'especialidad_code' => $t->especialidad_code,
            'numero' => $t->numero,
            'titulo' => $t->titulo,
            'status' => $t->status,
            'notas' => $t->notas,
            'last_studied_at' => $t->last_studied_at?->toIso8601String(),
            'es_oficial' => (bool) $t->es_oficial,
            'tema_oficial_id' => $t->tema_oficial_id,
            'tiene_esquema' => $t->tema_oficial_id !== null,
            'esquema_progreso' => $t->esquema_progreso ?? [],
            'score' => $t->score,
        ];
    }

    /** @return array<string, mixed> */
    private function sesionArray(OposicionSesion $s): array
    {
        return [
            'id' => $s->id,
            'fecha' => $s->fecha?->toDateString(),
            'minutos' => $s->minutos,
            'temas_estudiados' => $s->temas_estudiados ?? [],
            'notas' => $s->notas,
            'created_at' => $s->created_at?->toIso8601String(),
        ];
    }
}
