<?php

namespace App\Http\Controllers\Api\Docente;

use App\Http\Controllers\Controller;
use App\Models\DocenteGrupo;
use App\Models\DocenteSesion;
use App\Models\DocenteUnidad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SesionesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = DocenteSesion::with(['grupo.asignatura', 'unidad'])
            ->where('user_id', $request->user()->id);

        if ($request->filled('grupo_id')) {
            $q->where('grupo_id', $request->integer('grupo_id'));
        }
        if ($request->filled('fecha')) {
            $q->whereDate('fecha', $request->input('fecha'));
        }
        if ($request->filled('impartida')) {
            $q->where('impartida', $request->boolean('impartida'));
        }

        return response()->json(['data' => $q->orderBy('fecha')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'grupo_id' => ['required', 'integer'],
            'unidad_id' => ['nullable', 'integer'],
            'fecha' => ['required', 'date'],
            'titulo_planificado' => ['required', 'string', 'max:200'],
            'contenido_real' => ['nullable', 'string'],
            'observaciones' => ['nullable', 'string'],
        ]);

        DocenteGrupo::where('user_id', $request->user()->id)->findOrFail($data['grupo_id']);

        $sesion = DocenteSesion::create(array_merge($data, ['user_id' => $request->user()->id]));

        return response()->json($sesion->load(['grupo.asignatura', 'unidad']), 201);
    }

    public function update(Request $request, DocenteSesion $sesion): JsonResponse
    {
        abort_if($sesion->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'fecha' => ['sometimes', 'date'],
            'titulo_planificado' => ['sometimes', 'string', 'max:200'],
            'contenido_real' => ['nullable', 'string'],
            'impartida' => ['sometimes', 'boolean'],
            'observaciones' => ['nullable', 'string'],
        ]);

        if (isset($data['impartida']) && $data['impartida'] && ! $sesion->impartida) {
            $data['impartida_at'] = Carbon::now();
        }

        $sesion->update($data);

        return response()->json($sesion->load(['grupo.asignatura', 'unidad']));
    }

    /** Progreso del grupo: sesiones impartidas, % completado, UD actual. */
    public function progresoGrupo(Request $request, DocenteGrupo $grupo): JsonResponse
    {
        abort_if($grupo->user_id !== $request->user()->id, 403);

        $total = DocenteSesion::where('grupo_id', $grupo->id)->count();
        $impartidas = DocenteSesion::where('grupo_id', $grupo->id)->where('impartida', true)->count();
        $pct = $total > 0 ? round(($impartidas / $total) * 100) : 0;

        $ultimaUnidad = DocenteSesion::with('unidad')
            ->where('grupo_id', $grupo->id)
            ->where('impartida', true)
            ->orderByDesc('impartida_at')
            ->first()?->unidad;

        // Proyección simple: promedio sesiones/día × sesiones restantes
        $restantes = $total - $impartidas;
        $proyeccion = null;
        if ($impartidas > 0 && $restantes > 0) {
            $primera = DocenteSesion::where('grupo_id', $grupo->id)->where('impartida', true)->min('impartida_at');
            $dias = max(1, Carbon::parse($primera)->diffInDays(Carbon::now()));
            $ritmo = $impartidas / $dias; // sesiones por día
            if ($ritmo > 0) {
                $proyeccion = Carbon::now()->addDays((int) ceil($restantes / $ritmo))->toDateString();
            }
        }

        $estado = match (true) {
            $pct >= 90 => 'al_dia',
            $pct >= 70 => 'retraso_leve',
            default => 'riesgo',
        };

        return response()->json([
            'grupo_id' => $grupo->id,
            'nombre' => $grupo->nombre,
            'total_sesiones' => $total,
            'impartidas' => $impartidas,
            'porcentaje' => $pct,
            'estado' => $estado,
            'unidad_actual' => $ultimaUnidad,
            'proyeccion_fin' => $proyeccion,
        ]);
    }
}
