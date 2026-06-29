<?php

namespace App\Http\Controllers\Api\Docente;

use App\Http\Controllers\Controller;
use App\Models\DocenteProgramacion;
use App\Services\DocenteAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgramacionesController extends Controller
{
    public function __construct(private readonly DocenteAiService $ai) {}

    public function index(Request $request): JsonResponse
    {
        $programaciones = DocenteProgramacion::with('asignatura')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->get();

        return response()->json(['data' => $programaciones]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'asignatura_id' => ['required', 'integer'],
            'titulo' => ['required', 'string', 'max:200'],
            'año_academico' => ['required', 'string', 'max:9'],
            'centro_nombre' => ['nullable', 'string', 'max:200'],
            'centro_tipo' => ['nullable', 'string', 'max:20'],
            'es_bilingue' => ['boolean'],
            'objetivos_generales' => ['nullable', 'string'],
            'metodologia' => ['nullable', 'string'],
            'atencion_diversidad' => ['nullable', 'string'],
            'criterios_evaluacion' => ['nullable', 'string'],
            'instrumentos_evaluacion' => ['nullable', 'string'],
            'status' => ['in:borrador,activa,archivada'],
            'document_id' => ['nullable', 'integer'],
        ]);

        $p = DocenteProgramacion::create(array_merge($data, ['user_id' => $request->user()->id]));

        return response()->json($p->load('asignatura'), 201);
    }

    public function show(Request $request, DocenteProgramacion $programacion): JsonResponse
    {
        abort_if($programacion->user_id !== $request->user()->id, 403);

        return response()->json($programacion->load(['asignatura', 'unidades']));
    }

    public function update(Request $request, DocenteProgramacion $programacion): JsonResponse
    {
        abort_if($programacion->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'titulo' => ['sometimes', 'string', 'max:200'],
            'centro_nombre' => ['nullable', 'string', 'max:200'],
            'centro_tipo' => ['nullable', 'string', 'max:20'],
            'es_bilingue' => ['boolean'],
            'objetivos_generales' => ['nullable', 'string'],
            'metodologia' => ['nullable', 'string'],
            'atencion_diversidad' => ['nullable', 'string'],
            'criterios_evaluacion' => ['nullable', 'string'],
            'instrumentos_evaluacion' => ['nullable', 'string'],
            'status' => ['in:borrador,activa,archivada'],
            'document_id' => ['nullable', 'integer'],
        ]);

        $programacion->update($data);

        return response()->json($programacion->load('asignatura'));
    }

    public function destroy(Request $request, DocenteProgramacion $programacion): JsonResponse
    {
        abort_if($programacion->user_id !== $request->user()->id, 403);
        $programacion->update(['status' => 'archivada']);

        return response()->json(['archived' => true]);
    }

    public function adaptar(Request $request, DocenteProgramacion $programacion): JsonResponse
    {
        abort_if($programacion->user_id !== $request->user()->id, 403);

        $params = $request->validate([
            'nombre' => ['required', 'string', 'max:200'],
            'tipo' => ['required', 'string', 'max:20'],
            'es_bilingue' => ['boolean'],
            'localidad' => ['nullable', 'string'],
        ]);

        $result = $this->ai->adaptarProgramacion($request->user()->id, $programacion->id, $params);

        return response()->json($result);
    }

    public function duplicar(Request $request, DocenteProgramacion $programacion): JsonResponse
    {
        abort_if($programacion->user_id !== $request->user()->id, 403);

        $nuevoAnyo = $request->validate(['año_academico' => ['required', 'string', 'max:9']])['año_academico'];

        $nueva = $programacion->replicate();
        $nueva->año_academico = $nuevoAnyo;
        $nueva->status = 'borrador';
        $nueva->save();

        foreach ($programacion->unidades as $ud) {
            $nuevaUd = $ud->replicate();
            $nuevaUd->programacion_id = $nueva->id;
            $nuevaUd->user_id = $request->user()->id;
            $nuevaUd->save();
        }

        return response()->json($nueva->load(['asignatura', 'unidades']), 201);
    }
}
