<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\DocenteAsignatura;
use App\Models\DocenteExamen;
use App\Models\DocenteProgramacion;
use App\Models\DocenteRecursoCompartido;
use App\Models\DocenteRubrica;
use App\Models\DocenteSituacionAprendizaje;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocenteMetricasController extends Controller
{
    public function stats(): JsonResponse
    {
        $usuariosDocente = User::where('modo_activo', 'docente')->count();

        $programaciones = [
            'total' => DocenteProgramacion::count(),
            'activas' => DocenteProgramacion::where('status', 'activa')->count(),
            'archivadas' => DocenteProgramacion::where('status', 'archivada')->count(),
        ];

        $recursos = [
            'rubricas' => DocenteRubrica::count(),
            'situaciones' => DocenteSituacionAprendizaje::count(),
            'examenes' => DocenteExamen::count(),
        ];

        $banco = [
            'total_compartidos' => DocenteRecursoCompartido::count(),
            'pendientes_moderacion' => DocenteRecursoCompartido::where('moderado', false)->count(),
            'moderados' => DocenteRecursoCompartido::where('moderado', true)->count(),
        ];

        return response()->json([
            'usuarios_docente' => $usuariosDocente,
            'programaciones' => $programaciones,
            'recursos' => $recursos,
            'banco' => $banco,
        ]);
    }

    public function pendientesModeracion(): JsonResponse
    {
        $pendientes = DocenteRecursoCompartido::where('moderado', false)
            ->with('autor:id,name,email')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'tipo' => $r->tipo,
                'recurso_id' => $r->recurso_id,
                'autor' => $r->autor?->name ?? 'Anónimo',
                'created_at' => $r->created_at,
            ]);

        return response()->json(['data' => $pendientes]);
    }

    public function moderar(Request $request, DocenteRecursoCompartido $recurso): JsonResponse
    {
        $data = $request->validate([
            'aprobar' => ['required', 'boolean'],
        ]);

        if ($data['aprobar']) {
            $recurso->update(['moderado' => true]);
        } else {
            $recurso->delete();
        }

        return response()->json(['moderado' => $data['aprobar']]);
    }
}
