<?php

namespace App\Services;

use App\Models\OposicionTema;
use Illuminate\Support\Carbon;

/**
 * Per-tema mastery scoring. Combines flashcard, simulacro and chat-evaluation
 * results into a weighted 0-100 score, blended with the prior score so recent
 * sessions count more.
 */
class ScoringService
{
    private const WEIGHTS = ['flashcards' => 0.30, 'simulacro' => 0.50, 'chat_evaluation' => 0.20];
    private const RELIABLE_MIN_SESSIONS = 3;

    /**
     * Record a session result and recompute the tema's score.
     *
     * @param  array{correct?:int, total?:int, score?:int}  $results
     */
    public function updateTemaScore(OposicionTema $tema, string $sessionType, array $results): OposicionTema
    {
        $sessionScore = $this->sessionScore($results);
        $weight = self::WEIGHTS[$sessionType] ?? 0.30;

        $breakdown = $tema->score_breakdown ?? [];
        // Recency: blend the new session into the running per-type average (60/40).
        $prev = $breakdown[$sessionType] ?? null;
        $breakdown[$sessionType] = $prev === null ? $sessionScore : (int) round($prev * 0.4 + $sessionScore * 0.6);

        // Weighted mean across the types we have data for (re-normalised).
        $num = $den = 0.0;
        foreach (self::WEIGHTS as $type => $w) {
            if (isset($breakdown[$type])) {
                $num += $breakdown[$type] * $w;
                $den += $w;
            }
        }
        $score = $den > 0 ? (int) round($num / $den) : $sessionScore;

        $tema->forceFill([
            'score' => max(0, min(100, $score)),
            'score_sessions' => (int) $tema->score_sessions + 1,
            'score_updated_at' => Carbon::now(),
            'score_breakdown' => $breakdown,
            'last_studied_at' => Carbon::now(),
        ])->save();

        return $tema->fresh();
    }

    /**
     * All temas with score + recommendation, worst first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTemaScores(int $userId, ?string $especialidad = null): array
    {
        return OposicionTema::where('user_id', $userId)
            ->when($especialidad, fn ($q, $c) => $q->where('especialidad_code', $c))
            ->get()
            ->sortBy(fn ($t) => $t->score ?? -1)
            ->map(fn ($t) => $this->present($t))
            ->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function present(OposicionTema $t): array
    {
        return [
            'tema_id' => $t->id,
            'numero' => $t->numero,
            'titulo' => $t->titulo,
            'status' => $t->status,
            'score' => $t->score,
            'score_sessions' => $t->score_sessions,
            'reliable' => (int) $t->score_sessions >= self::RELIABLE_MIN_SESSIONS,
            'score_breakdown' => $t->score_breakdown,
            'recommendation' => $this->recommendation($t->score),
            'score_updated_at' => $t->score_updated_at,
        ];
    }

    private function sessionScore(array $results): int
    {
        if (isset($results['score'])) {
            return max(0, min(100, (int) $results['score']));
        }
        $total = max(1, (int) ($results['total'] ?? 0));
        $correct = (int) ($results['correct'] ?? 0);

        return (int) round(($correct / $total) * 100);
    }

    private function recommendation(?int $score): string
    {
        return match (true) {
            $score === null => 'Sin evaluar — haz una sesión para medir tu nivel',
            $score < 40 => 'Prioritario — dedica al menos 3 sesiones esta semana',
            $score < 70 => 'Reforzar — practica con flashcards',
            $score < 90 => 'Bien — repaso ligero cada 2 semanas',
            default => 'Dominado — mantén con repaso mensual',
        };
    }
}
