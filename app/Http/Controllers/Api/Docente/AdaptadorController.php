<?php

namespace App\Http\Controllers\Api\Docente;

use App\Http\Controllers\Controller;
use App\Services\DocenteAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdaptadorController extends Controller
{
    public function __construct(private readonly DocenteAiService $ai) {}

    public function adaptarTexto(Request $request): JsonResponse
    {
        $data = $request->validate([
            'texto' => ['required', 'string'],
            'nivel_original' => ['required', 'string', 'max:30'],
            'nivel_destino' => ['required', 'string', 'max:30'],
            'tipo_adaptacion' => ['required', 'in:simplificar,ampliar,ACIS'],
        ]);

        // PART 8 — legal: block student personal data patterns
        if (preg_match('/\b(DNI|NIE|NIF|[A-Z]\d{7}[A-Z]|\bexpediente\b|\bnotas?\b.*alumno|\balumno.*notas?\b)/i', $data['texto'])) {
            return response()->json([
                'error' => 'El texto parece contener datos personales de alumnos. Por protección de datos, no se puede procesar.',
            ], 422);
        }

        try {
            $result = $this->ai->adaptarTexto($request->user()->id, $data['texto'], $data);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(array_merge($result, [
            'disclaimer' => 'Texto generado por IA. Debe ser revisado y validado por el docente antes de su uso.',
        ]));
    }
}
