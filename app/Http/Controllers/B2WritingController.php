<?php
namespace App\Http\Controllers;

use App\Models\B2Exercise;
use App\Models\B2ExerciseHistory;
use App\Models\B2SkillProgress;
use App\Models\B2UserProfile;
use App\Models\B2UserStreak;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class B2WritingController extends Controller
{
    public function submitWriting(Request $request, int $exerciseId): JsonResponse
    {
        $exercise = B2Exercise::where('skill', 'writing')->findOrFail($exerciseId);
        $user = $request->user();

        $request->validate([
            'text' => 'required|string|min:10|max:5000',
            'time_spent_seconds' => 'nullable|integer|min:0',
        ]);

        $userText = $request->input('text');
        $wordCount = str_word_count($userText);

        // Try AI evaluation first, fallback to heuristic
        $aiResult = $this->evaluateWithAI($userText, $exercise);
        $score = $aiResult['score'] ?? $this->evaluateWriting($userText, $wordCount, $exercise);
        $feedback = $aiResult['feedback'] ?? $this->buildFeedback($score, $wordCount);
        $explanation = $aiResult['explanation'] ?? null;
        $aiDone = isset($aiResult['score']);

        $isCorrect = $score['total'] >= 60;
        $pointsEarned = $isCorrect ? round(($score['total'] / 100) * $exercise->points_reward) : 0;

        $historyRecord = B2ExerciseHistory::create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'skill' => 'writing',
            'user_answer' => $userText,
            'is_correct' => $isCorrect,
            'points_earned' => $pointsEarned,
            'feedback_ia' => is_array($feedback) ? json_encode($feedback) : $feedback,
            'time_spent_seconds' => $request->input('time_spent_seconds'),
            'score_percentage' => $score['total'],
            'ai_evaluation_scores' => $score,
            'ai_evaluation_feedback' => is_array($feedback) ? implode("\n", $feedback) : $feedback,
            'ai_evaluation_done' => $aiDone,
            'ai_evaluation_model' => $aiResult['model'] ?? null,
            'ai_tokens_used' => $aiResult['tokens_used'] ?? null,
        ]);

        $this->updateProgress($user->id, 'writing', $isCorrect, $pointsEarned);

        return response()->json([
            'score' => $score,
            'is_correct' => $isCorrect,
            'points_earned' => $pointsEarned,
            'feedback' => $feedback,
            'explanation' => $explanation,
            'word_count' => $wordCount,
            'ai_evaluated' => $aiDone,
            'history_id' => $historyRecord->id,
        ]);
    }

    private function evaluateWithAI(string $text, B2Exercise $exercise): array
    {
        $apiKey = config('services.anthropic.key') ?? env('ANTHROPIC_API_KEY');
        if (!$apiKey) return [];

        $prompt = "You are a Cambridge B2 First examiner. Evaluate this writing task.\n\n"
            . "TASK: {$exercise->user_input_instruction}\n\n"
            . "STUDENT RESPONSE:\n{$text}\n\n"
            . "Evaluate on a 0-100 scale for each criterion and return JSON:\n"
            . '{"content": <0-100>, "organisation": <0-100>, "language": <0-100>, "communicative": <0-100>, '
            . '"total": <0-100>, "feedback": {"strength": "...", "improvement": "...", "tip": "..."}, '
            . '"explanation": "2-3 sentence overall assessment in Spanish"}';

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-haiku-4-5-20251001',
                'max_tokens' => 600,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['content'][0]['text'] ?? '';
                // Extract JSON from response
                if (preg_match('/\{.*\}/s', $content, $matches)) {
                    $parsed = json_decode($matches[0], true);
                    if ($parsed && isset($parsed['total'])) {
                        return [
                            'score' => [
                                'content' => (int)($parsed['content'] ?? 60),
                                'organisation' => (int)($parsed['organisation'] ?? 60),
                                'language' => (int)($parsed['language'] ?? 60),
                                'communicative' => (int)($parsed['communicative'] ?? 60),
                                'total' => (int)$parsed['total'],
                            ],
                            'feedback' => $parsed['feedback'] ?? [],
                            'explanation' => $parsed['explanation'] ?? null,
                            'model' => 'claude-haiku-4-5-20251001',
                            'tokens_used' => ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0),
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('AI writing evaluation failed', ['error' => $e->getMessage()]);
        }

        return [];
    }

    private function evaluateWriting(string $text, int $wordCount, B2Exercise $exercise): array
    {
        $content = min(100, ($wordCount / 150) * 100); // target 150 words
        $organisation = mb_substr_count($text, '.') >= 5 ? 75 : 50;
        $language = $this->estimateLanguageScore($text);
        $communicative = $wordCount >= 100 ? 70 : 40;

        $total = round(($content + $organisation + $language + $communicative) / 4);

        return compact('content', 'organisation', 'language', 'communicative', 'total');
    }

    private function estimateLanguageScore(string $text): int
    {
        $hasConnectors = preg_match('/\b(however|therefore|furthermore|although|consequently|moreover)\b/i', $text);
        $hasComplex = preg_match('/\b(which|who|whose|where|when)\b/i', $text);
        $baseScore = 55;
        if ($hasConnectors) $baseScore += 20;
        if ($hasComplex) $baseScore += 10;
        return min(100, $baseScore);
    }

    private function buildFeedback(array $score, int $wordCount): array
    {
        return [
            'content' => $score['content'] >= 70 ? 'Good content coverage.' : 'Try to develop your ideas more fully.',
            'organisation' => $score['organisation'] >= 70 ? 'Well organised.' : 'Use more connecting phrases and paragraphs.',
            'language' => $score['language'] >= 70 ? 'Good language use.' : 'Try to use more varied vocabulary and structures.',
            'communicative' => $score['communicative'] >= 70 ? 'Communicates effectively.' : 'Aim for at least 100-150 words.',
            'word_count_note' => "You wrote {$wordCount} words.",
        ];
    }

    private function updateProgress(int $userId, string $skill, bool $isCorrect, int $points): void
    {
        $sp = B2SkillProgress::firstOrCreate(
            ['user_id' => $userId, 'skill' => $skill],
            ['current_score' => 0, 'exercises_completed' => 0, 'exercises_correct' => 0]
        );
        $sp->exercises_completed++;
        if ($isCorrect) $sp->exercises_correct++;
        $sp->accuracy_percentage = round(($sp->exercises_correct / $sp->exercises_completed) * 100, 1);
        $increment = $isCorrect ? 2 : -1;
        $sp->current_score = max(0, min(100, $sp->current_score + $increment));
        $sp->mastery_level = $sp->getMasteryLevel();
        $sp->last_practiced_at = now();
        $sp->save();

        $profile = B2UserProfile::firstOrCreate(['user_id' => $userId]);
        $profile->writing_score = $sp->current_score;
        $profile->updateOverallScore();

        $streak = B2UserStreak::firstOrCreate(['user_id' => $userId]);
        $streak->updateStreak();
    }
}
