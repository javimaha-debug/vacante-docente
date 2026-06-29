<?php

namespace App\Http\Controllers\Api\Docente;

use App\Http\Controllers\Controller;
use App\Models\DocenteRecursoCompartido;
use App\Models\DocenteRubrica;
use App\Services\DocenteAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RubricasController extends Controller
{
    public function __construct(private readonly DocenteAiService $ai) {}

    public function index(Request $request): JsonResponse
    {
        $rubricas = DocenteRubrica::where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->get();

        return response()->json(['data' => $rubricas]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:200'],
            'descripcion' => ['nullable', 'string'],
            'tipo_tarea' => ['nullable', 'string', 'max:60'],
            'etapa' => ['nullable', 'in:infantil,primaria,eso,bachillerato,fp,otros'],
            'asignatura_id' => ['nullable', 'integer'],
            'criterios' => ['required', 'array'],
        ]);

        $rubrica = DocenteRubrica::create(array_merge($data, ['user_id' => $request->user()->id]));

        return response()->json($rubrica, 201);
    }

    public function generar(Request $request): JsonResponse
    {
        $params = $request->validate([
            'tipo_tarea' => ['required', 'string'],
            'asignatura' => ['required', 'string'],
            'curso' => ['required', 'string'],
            'competencias' => ['nullable', 'array'],
            'num_criterios' => ['nullable', 'integer', 'min:2', 'max:10'],
        ]);

        $result = $this->ai->generateRubrica($request->user()->id, $params);

        return response()->json($result);
    }

    public function update(Request $request, DocenteRubrica $rubrica): JsonResponse
    {
        abort_if($rubrica->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'titulo' => ['sometimes', 'string', 'max:200'],
            'descripcion' => ['nullable', 'string'],
            'tipo_tarea' => ['nullable', 'string', 'max:60'],
            'criterios' => ['sometimes', 'array'],
        ]);

        $rubrica->update($data);

        return response()->json($rubrica);
    }

    public function destroy(Request $request, DocenteRubrica $rubrica): JsonResponse
    {
        abort_if($rubrica->user_id !== $request->user()->id, 403);
        $rubrica->delete();

        return response()->json(['deleted' => true]);
    }

    public function compartir(Request $request, DocenteRubrica $rubrica): JsonResponse
    {
        abort_if($rubrica->user_id !== $request->user()->id, 403);

        $rubrica->update(['es_publica' => true]);

        $compartido = DocenteRecursoCompartido::firstOrCreate([
            'user_id' => $request->user()->id,
            'tipo' => 'rubrica',
            'recurso_id' => $rubrica->id,
        ]);

        return response()->json(['compartido' => true, 'pendiente_moderacion' => ! $compartido->moderado]);
    }
}
