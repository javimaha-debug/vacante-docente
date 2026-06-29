<?php

namespace App\Http\Controllers\Api\Docente;

use App\Http\Controllers\Controller;
use App\Models\DocenteExamen;
use App\Services\DocenteAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamenesController extends Controller
{
    public function __construct(private readonly DocenteAiService $ai) {}

    public function index(Request $request): JsonResponse
    {
        $examenes = DocenteExamen::where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->get();

        return response()->json(['data' => $examenes]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:200'],
            'tipo' => ['required', 'in:test,desarrollo,mixto,oral'],
            'tiempo_minutos' => ['nullable', 'integer', 'min:5'],
            'instrucciones' => ['nullable', 'string'],
            'preguntas' => ['required', 'array'],
            'asignatura_id' => ['nullable', 'integer'],
            'unidad_id' => ['nullable', 'integer'],
        ]);

        $examen = DocenteExamen::create(array_merge($data, ['user_id' => $request->user()->id]));

        return response()->json($examen, 201);
    }

    public function generar(Request $request): JsonResponse
    {
        $params = $request->validate([
            'asignatura_id' => ['nullable', 'integer'],
            'asignatura' => ['nullable', 'string'],
            'unidad_id' => ['nullable', 'integer'],
            'unidad' => ['nullable', 'string'],
            'tipo' => ['required', 'in:test,desarrollo,mixto,oral'],
            'num_preguntas' => ['nullable', 'integer', 'min:3', 'max:30'],
            'tiempo_minutos' => ['nullable', 'integer'],
            'dificultad' => ['nullable', 'in:baja,media,alta'],
        ]);

        $result = $this->ai->generateExamen($request->user()->id, $params);

        return response()->json($result);
    }

    public function destroy(Request $request, DocenteExamen $examen): JsonResponse
    {
        abort_if($examen->user_id !== $request->user()->id, 403);
        $examen->delete();

        return response()->json(['deleted' => true]);
    }

    /** Export placeholder — actual PDF generation would require a library like DomPDF. */
    public function export(Request $request, DocenteExamen $examen): JsonResponse
    {
        abort_if($examen->user_id !== $request->user()->id, 403);

        return response()->json([
            'examen' => $examen,
            'export_note' => 'Exportación PDF disponible próximamente.',
        ]);
    }
}
