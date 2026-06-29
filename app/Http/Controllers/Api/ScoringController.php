<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OposicionTema;
use App\Services\ScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScoringController extends Controller
{
    public function index(Request $request, ScoringService $scoring): JsonResponse
    {
        $scores = $scoring->getTemaScores($request->user()->id, $request->query('especialidad_code'));

        $reliable = collect($scores)->where('reliable', true);

        return response()->json([
            'data' => $scores,
            'resumen' => [
                'total' => count($scores),
                'dominados' => collect($scores)->where('status', 'dominado')->count(),
                'en_progreso' => collect($scores)->where('status', 'en_progreso')->count(),
                'flojos' => $reliable->filter(fn ($s) => ($s['score'] ?? 0) < 40)->count(),
            ],
        ]);
    }

    public function show(Request $request, int $tema, ScoringService $scoring): JsonResponse
    {
        $model = OposicionTema::where('id', $tema)->where('user_id', $request->user()->id)->firstOrFail();

        return response()->json($scoring->present($model));
    }

    public function simulacro(Request $request, int $tema, ScoringService $scoring): JsonResponse
    {
        $data = $request->validate([
            'correct' => ['required_without:score', 'integer', 'min:0'],
            'total' => ['required_without:score', 'integer', 'min:1'],
            'score' => ['sometimes', 'integer', 'min:0', 'max:100'],
        ]);

        $model = OposicionTema::where('id', $tema)->where('user_id', $request->user()->id)->firstOrFail();
        $updated = $scoring->updateTemaScore($model, 'simulacro', $data);

        return response()->json($scoring->present($updated));
    }
}
