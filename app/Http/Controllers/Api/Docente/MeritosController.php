<?php

namespace App\Http\Controllers\Api\Docente;

use App\Http\Controllers\Controller;
use App\Models\DocenteMerito;
use App\Services\DocenteAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeritosController extends Controller
{
    public function __construct(private readonly DocenteAiService $ai) {}

    public function index(Request $request): JsonResponse
    {
        $meritos = DocenteMerito::where('user_id', $request->user()->id)
            ->orderBy('tipo')
            ->orderByDesc('fecha_inicio')
            ->get()
            ->groupBy('tipo');

        return response()->json(['data' => $meritos]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tipo' => ['required', 'in:formacion,publicacion,cargo,actividad_complementaria,otro'],
            'titulo' => ['required', 'string', 'max:200'],
            'organismo' => ['nullable', 'string', 'max:150'],
            'horas' => ['nullable', 'integer', 'min:1'],
            'creditos_ects' => ['nullable', 'numeric', 'min:0'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'document_id' => ['nullable', 'integer'],
        ]);

        $merito = DocenteMerito::create(array_merge($data, ['user_id' => $request->user()->id]));

        return response()->json($merito, 201);
    }

    public function update(Request $request, DocenteMerito $merito): JsonResponse
    {
        abort_if($merito->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'tipo' => ['sometimes', 'in:formacion,publicacion,cargo,actividad_complementaria,otro'],
            'titulo' => ['sometimes', 'string', 'max:200'],
            'organismo' => ['nullable', 'string', 'max:150'],
            'horas' => ['nullable', 'integer', 'min:1'],
            'creditos_ects' => ['nullable', 'numeric', 'min:0'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date'],
            'document_id' => ['nullable', 'integer'],
        ]);

        $merito->update($data);

        return response()->json($merito);
    }

    public function destroy(Request $request, DocenteMerito $merito): JsonResponse
    {
        abort_if($merito->user_id !== $request->user()->id, 403);
        $merito->delete();

        return response()->json(['deleted' => true]);
    }

    public function baremo(Request $request): JsonResponse
    {
        $result = $this->ai->calcularMeritos($request->user()->id);

        return response()->json($result);
    }

    public function export(Request $request): JsonResponse
    {
        $meritos = DocenteMerito::where('user_id', $request->user()->id)
            ->orderBy('tipo')->orderByDesc('fecha_inicio')
            ->get();

        return response()->json([
            'meritos' => $meritos,
            'export_note' => 'Exportación PDF del currículum disponible próximamente.',
        ]);
    }
}
