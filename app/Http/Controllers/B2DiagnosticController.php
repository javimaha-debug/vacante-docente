<?php
namespace App\Http\Controllers;

use App\Models\B2DiagnosticResult;
use App\Models\B2UserProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class B2DiagnosticController extends Controller
{
    private const BANDS = [
        ['min' => 0,  'max' => 19,  'band' => 'A1→A2',  'label' => 'Principiante'],
        ['min' => 20, 'max' => 39,  'band' => 'A2→B1',  'label' => 'Elemental'],
        ['min' => 40, 'max' => 59,  'band' => 'B1→B2',  'label' => 'Intermedio'],
        ['min' => 60, 'max' => 79,  'band' => 'B2_ready','label' => 'B2 en progreso'],
        ['min' => 80, 'max' => 100, 'band' => 'B2→C1',  'label' => 'Avanzado'],
    ];

    public function start(Request $request): JsonResponse
    {
        $diagnosticData = json_decode(
            file_get_contents(storage_path('app/b2/b2_diagnostic_test.json')),
            true
        );

        Cache::put(
            "b2_diagnostic_{$request->user()->id}",
            ['answers' => [], 'started_at' => now()->toISOString()],
            now()->addHours(2)
        );

        return response()->json([
            'test' => $diagnosticData['diagnostic_test_b2'],
            'scoring_bands' => $diagnosticData['scoring_bands'],
        ]);
    }

    public function completeDiagnostic(Request $request): JsonResponse
    {
        $request->validate([
            'reading_score' => 'required|integer|min:0|max:15',
            'grammar_score' => 'required|integer|min:0|max:16',
            'listening_score' => 'required|integer|min:0|max:8',
            'writing_score' => 'required|integer|min:0|max:5',
            'speaking_score' => 'required|integer|min:0|max:5',
        ]);

        $userId = $request->user()->id;
        $r = $request->integer('reading_score');
        $g = $request->integer('grammar_score');
        $l = $request->integer('listening_score');
        $w = $request->integer('writing_score');
        $s = $request->integer('speaking_score');

        // Normalize each skill to 0-100 for profile
        $readingPct = round(($r / 15) * 100);
        $grammarPct = round(($g / 16) * 100);
        $listeningPct = round(($l / 8) * 100);
        $writingPct = round(($w / 5) * 100);
        $speakingPct = round(($s / 5) * 100);
        $totalPct = round(($readingPct + $grammarPct + $listeningPct + $writingPct + $speakingPct) / 5);

        $band = $this->assignBand($totalPct);
        $weaknesses = $this->findWeaknesses(['reading' => $readingPct, 'grammar' => $grammarPct, 'listening' => $listeningPct, 'writing' => $writingPct, 'speaking' => $speakingPct]);

        B2DiagnosticResult::create([
            'user_id' => $userId,
            'reading_score' => $r,
            'grammar_score' => $g,
            'listening_score' => $l,
            'writing_score' => $w,
            'speaking_score' => $s,
            'total_score' => $totalPct,
            'band_assigned' => $band,
            'weaknesses' => $weaknesses,
            'recommendations' => $this->getRecommendations($weaknesses),
            'completed_at' => now(),
        ]);

        $profile = B2UserProfile::updateOrCreate(
            ['user_id' => $userId],
            [
                'reading_score' => $readingPct,
                'grammar_score' => $grammarPct,
                'writing_score' => $writingPct,
                'listening_score' => $listeningPct,
                'speaking_score' => $speakingPct,
                'overall_score' => $totalPct,
                'diagnostic_band' => $band,
                'diagnostic_completed_at' => now(),
            ]
        );

        return response()->json([
            'band' => $band,
            'total_score' => $totalPct,
            'skills' => [
                'reading' => $readingPct,
                'grammar' => $grammarPct,
                'listening' => $listeningPct,
                'writing' => $writingPct,
                'speaking' => $speakingPct,
            ],
            'weaknesses' => $weaknesses,
        ]);
    }

    public function getResult(Request $request): JsonResponse
    {
        $result = B2DiagnosticResult::where('user_id', $request->user()->id)
            ->orderByDesc('completed_at')
            ->first();

        if (!$result) {
            return response()->json(['has_diagnostic' => false]);
        }

        return response()->json(['has_diagnostic' => true, 'result' => $result]);
    }

    private function assignBand(int $score): string
    {
        foreach (self::BANDS as $b) {
            if ($score >= $b['min'] && $score <= $b['max']) return $b['band'];
        }
        return 'B2_ready';
    }

    private function findWeaknesses(array $skills): array
    {
        return array_keys(array_filter($skills, fn($s) => $s < 60));
    }

    private function getRecommendations(array $weaknesses): array
    {
        $tips = [
            'reading' => 'Practica comprensión lectora con textos B2. Lee artículos en inglés 15 min/día.',
            'grammar' => 'Enfócate en phrasal verbs, condicionales y tiempos verbales.',
            'listening' => 'Escucha podcasts BBC en inglés. Practica dictados cortos.',
            'writing' => 'Escribe emails formales e informales. Practica estructuras de ensayo.',
            'speaking' => 'Grábate hablando 2 minutos sobre un tema. Trabaja la fluidez.',
        ];
        return array_map(fn($w) => $tips[$w] ?? '', $weaknesses);
    }
}
