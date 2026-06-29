<?php

namespace App\Http\Controllers\Api\Docente;

use App\Http\Controllers\Controller;
use App\Models\DocenteGrupo;
use App\Models\DocenteHorario;
use App\Models\DocenteSesion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class HorarioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $horario = DocenteHorario::with('grupo.asignatura')
            ->where('user_id', $request->user()->id)
            ->orderByRaw("FIELD(dia_semana,'lunes','martes','miercoles','jueves','viernes')")
            ->orderBy('hora_inicio')
            ->get();

        return response()->json(['data' => $horario]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'grupo_id' => ['required', 'integer'],
            'dia_semana' => ['required', 'in:lunes,martes,miercoles,jueves,viernes'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'aula' => ['nullable', 'string', 'max:30'],
        ]);

        DocenteGrupo::where('user_id', $request->user()->id)->findOrFail($data['grupo_id']);

        $entrada = DocenteHorario::create(array_merge($data, ['user_id' => $request->user()->id]));

        return response()->json($entrada->load('grupo.asignatura'), 201);
    }

    public function update(Request $request, DocenteHorario $horario): JsonResponse
    {
        abort_if($horario->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'dia_semana' => ['sometimes', 'in:lunes,martes,miercoles,jueves,viernes'],
            'hora_inicio' => ['sometimes', 'date_format:H:i'],
            'hora_fin' => ['sometimes', 'date_format:H:i'],
            'aula' => ['nullable', 'string', 'max:30'],
        ]);

        $horario->update($data);

        return response()->json($horario->load('grupo.asignatura'));
    }

    public function destroy(Request $request, DocenteHorario $horario): JsonResponse
    {
        abort_if($horario->user_id !== $request->user()->id, 403);
        $horario->delete();

        return response()->json(['deleted' => true]);
    }

    /** Sesiones de la semana actual con su estado de impartición. */
    public function semana(Request $request): JsonResponse
    {
        $lunes = Carbon::now()->startOfWeek();
        $viernes = Carbon::now()->endOfWeek()->subDays(2); // Mon-Fri

        $sesiones = DocenteSesion::with(['grupo.asignatura', 'unidad'])
            ->where('user_id', $request->user()->id)
            ->whereBetween('fecha', [$lunes->toDateString(), $viernes->toDateString()])
            ->orderBy('fecha')->orderBy('id')
            ->get();

        return response()->json(['data' => $sesiones, 'semana_inicio' => $lunes->toDateString()]);
    }
}
