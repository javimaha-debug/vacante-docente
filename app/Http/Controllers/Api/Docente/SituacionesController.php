<?php

namespace App\Http\Controllers\Api\Docente;

use App\Http\Controllers\Controller;
use App\Models\DocenteRecursoCompartido;
use App\Models\DocenteSituacionAprendizaje;
use App\Services\DocenteAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SituacionesController extends Controller
{
    public function __construct(private readonly DocenteAiService $ai) {}

    public function index(Request $request): JsonResponse
    {
        $situaciones = DocenteSituacionAprendizaje::where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->get();

        return response()->json(['data' => $situaciones]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:200'],
            'descripcion' => ['nullable', 'string'],
            'contexto' => ['nullable', 'string'],
            'competencias_clave' => ['nullable', 'array'],
            'competencias_especificas' => ['nullable', 'array'],
            'saberes_basicos' => ['nullable', 'array'],
            'actividades' => ['nullable', 'array'],
            'criterios_evaluacion' => ['nullable', 'array'],
            'etapa' => ['nullable', 'in:infantil,primaria,eso,bachillerato,fp,otros'],
            'curso' => ['nullable', 'string', 'max:30'],
            'asignatura' => ['nullable', 'string', 'max:100'],
            'asignatura_id' => ['nullable', 'integer'],
        ]);

        $situacion = DocenteSituacionAprendizaje::create(array_merge($data, ['user_id' => $request->user()->id]));

        return response()->json($situacion, 201);
    }

    public function generar(Request $request): JsonResponse
    {
        $params = $request->validate([
            'asignatura' => ['required', 'string'],
            'curso' => ['required', 'string'],
            'competencias_clave' => ['nullable', 'array'],
            'contexto_mundo_real' => ['required', 'string'],
            'num_sesiones' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $result = $this->ai->generateSituacionAprendizaje($request->user()->id, $params);

        return response()->json($result);
    }

    public function update(Request $request, DocenteSituacionAprendizaje $situacion): JsonResponse
    {
        abort_if($situacion->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'titulo' => ['sometimes', 'string', 'max:200'],
            'descripcion' => ['nullable', 'string'],
            'actividades' => ['nullable', 'array'],
            'criterios_evaluacion' => ['nullable', 'array'],
        ]);

        $situacion->update($data);

        return response()->json($situacion);
    }

    public function destroy(Request $request, DocenteSituacionAprendizaje $situacion): JsonResponse
    {
        abort_if($situacion->user_id !== $request->user()->id, 403);
        $situacion->delete();

        return response()->json(['deleted' => true]);
    }

    public function compartir(Request $request, DocenteSituacionAprendizaje $situacion): JsonResponse
    {
        abort_if($situacion->user_id !== $request->user()->id, 403);

        $situacion->update(['es_publica' => true]);

        $compartido = DocenteRecursoCompartido::firstOrCreate([
            'user_id' => $request->user()->id,
            'tipo' => 'situacion_aprendizaje',
            'recurso_id' => $situacion->id,
        ]);

        return response()->json(['compartido' => true, 'pendiente_moderacion' => ! $compartido->moderado]);
    }
}
