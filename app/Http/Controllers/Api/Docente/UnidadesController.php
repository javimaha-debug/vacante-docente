<?php

namespace App\Http\Controllers\Api\Docente;

use App\Http\Controllers\Controller;
use App\Models\DocenteProgramacion;
use App\Models\DocenteSesion;
use App\Models\DocenteUnidad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class UnidadesController extends Controller
{
    public function index(Request $request, DocenteProgramacion $programacion): JsonResponse
    {
        abort_if($programacion->user_id !== $request->user()->id, 403);

        return response()->json(['data' => $programacion->unidades]);
    }

    public function store(Request $request, DocenteProgramacion $programacion): JsonResponse
    {
        abort_if($programacion->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'numero' => ['required', 'integer', 'min:1'],
            'titulo' => ['required', 'string', 'max:200'],
            'tipo' => ['in:unidad_didactica,situacion_aprendizaje,proyecto'],
            'descripcion' => ['nullable', 'string'],
            'competencias' => ['nullable', 'array'],
            'criterios_evaluacion' => ['nullable', 'array'],
            'num_sesiones_previstas' => ['integer', 'min:1'],
            'trimestre' => ['in:primero,segundo,tercero'],
            'tema_oficial_id' => ['nullable', 'integer'],
        ]);

        $ud = DocenteUnidad::create(array_merge($data, [
            'programacion_id' => $programacion->id,
            'user_id' => $request->user()->id,
        ]));

        return response()->json($ud, 201);
    }

    public function update(Request $request, DocenteUnidad $unidad): JsonResponse
    {
        abort_if($unidad->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'numero' => ['sometimes', 'integer', 'min:1'],
            'titulo' => ['sometimes', 'string', 'max:200'],
            'tipo' => ['sometimes', 'in:unidad_didactica,situacion_aprendizaje,proyecto'],
            'descripcion' => ['nullable', 'string'],
            'competencias' => ['nullable', 'array'],
            'criterios_evaluacion' => ['nullable', 'array'],
            'num_sesiones_previstas' => ['sometimes', 'integer', 'min:1'],
            'trimestre' => ['sometimes', 'in:primero,segundo,tercero'],
            'tema_oficial_id' => ['nullable', 'integer'],
        ]);

        $unidad->update($data);

        return response()->json($unidad);
    }

    public function destroy(Request $request, DocenteUnidad $unidad): JsonResponse
    {
        abort_if($unidad->user_id !== $request->user()->id, 403);
        $unidad->delete();

        return response()->json(['deleted' => true]);
    }

    /** Auto-generate planned sessions from the UD's num_sesiones_previstas. */
    public function generarSesiones(Request $request, DocenteUnidad $unidad): JsonResponse
    {
        abort_if($unidad->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'grupo_id' => ['required', 'integer'],
            'fecha_inicio' => ['required', 'date'],
        ]);

        $grupo = \App\Models\DocenteGrupo::where('user_id', $request->user()->id)->findOrFail($data['grupo_id']);

        // Get days this group has class this week (from horario)
        $diasClase = $grupo->horarios()->pluck('dia_semana')->unique()->values()->toArray();
        $diasMap = ['lunes' => 1, 'martes' => 2, 'miercoles' => 3, 'jueves' => 4, 'viernes' => 5];

        $sesiones = [];
        $fecha = Carbon::parse($data['fecha_inicio']);
        $count = 0;
        $maxDias = 90; // safety cap

        while ($count < $unidad->num_sesiones_previstas && $maxDias-- > 0) {
            $diaNom = strtolower($fecha->locale('es')->isoFormat('dddd'));
            // Normalize accents
            $diaNom = str_replace(['miércoles'], ['miercoles'], $diaNom);

            if (in_array($diaNom, $diasClase)) {
                $sesion = DocenteSesion::create([
                    'user_id' => $request->user()->id,
                    'grupo_id' => $grupo->id,
                    'unidad_id' => $unidad->id,
                    'fecha' => $fecha->toDateString(),
                    'titulo_planificado' => "Sesión " . ($count + 1) . ": {$unidad->titulo}",
                ]);
                $sesiones[] = $sesion;
                $count++;
            }
            $fecha->addDay();
        }

        return response()->json(['data' => $sesiones, 'generadas' => count($sesiones)], 201);
    }
}
