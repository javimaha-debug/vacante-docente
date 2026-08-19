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

class B2SpeakingController extends Controller
{
    public function submitSpeaking(Request $request, int $exerciseId): JsonResponse
    {
        $exercise = B2Exercise::where('skill', 'speaking')->findOrFail($exerciseId);
        $user = $request->user();

        $request->validate([
            'transcription' => 'required|string|min:5',
            'duration_seconds' => 'nullable|integer|min:5',
            'time_spent_seconds' => 'nullable|integer|min:0',
        ]);

        $transcription = $request->input('transcription');
        $duration = $request->input('duration_seconds', 60);
        $wordCount = str_word_count($transcription);

        // Try AI evaluation, fallback to heuristic
        $aiResult = $this->evaluateWithAI($transcription, $exercise);
        $score = $aiResult['score'] ?? $this->evaluateSpeaking($transcription, $wordCount, $duration);
        $feedback = $aiResult['feedback'] ?? $this->buildFeedback($score, $wordCount, $duration);
        $explanation = $aiResult['explanation'] ?? null;
        $aiDone = isset($aiResult['score']);

        $isCorrect = $score['total'] >= 60;
        $pointsEarned = $isCorrect ? round(($score['total'] / 100) * $exercise->points_reward) : 0;

        $historyRecord = B2ExerciseHistory::create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'skill' => 'speaking',
            'user_answer' => $transcription,
            'is_correct' => $isCorrect,
            'points_earned' => $pointsEarned,
            'time_spent_seconds' => $request->input('time_spent_seconds'),
            'score_percentage' => $score['total'],
            'ai_evaluation_scores' => $score,
            'ai_evaluation_feedback' => is_array($feedback) ? implode("\n", $feedback) : (string)$feedback,
            'ai_evaluation_done' => $aiDone,
            'ai_evaluation_model' => $aiResult['model'] ?? null,
            'ai_tokens_used' => $aiResult['tokens_used'] ?? null,
        ]);

        $this->updateProgress($user->id, 'speaking', $isCorrect, $pointsEarned);

        return response()->json([
            'score' => $score,
            'is_correct' => $isCorrect,
            'points_earned' => $pointsEarned,
            'word_count' => $wordCount,
            'feedback' => $feedback,
            'explanation' => $explanation,
            'ai_evaluated' => $aiDone,
            'history_id' => $historyRecord->id,
        ]);
    }

    private function evaluateWithAI(string $text, B2Exercise $exercise): array
    {
        $apiKey = config('services.anthropic.key') ?? env('ANTHROPIC_API_KEY');
        if (!$apiKey) return [];

        $prompt = "You are a Cambridge B2 speaking examiner. The student has provided a written transcript of their spoken response.\n\n"
            . "SPEAKING TASK: {$exercise->user_input_instruction}\n\n"
            . "STUDENT TRANSCRIPT:\n{$text}\n\n"
            . "Evaluate on a 0-100 scale and return JSON:\n"
            . '{"fluency": <0-100>, "vocabulary": <0-100>, "grammar": <0-100>, "interaction": <0-100>, '
            . '"total": <0-100>, "feedback": {"strength": "...", "improvement": "...", "tip": "..."}, '
            . '"explanation": "2-3 sentence overall assessment in Spanish"}';

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-haiku-4-5-20251001',
                'max_tokens' => 500,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['content'][0]['text'] ?? '';
                if (preg_match('/\{.*\}/s', $content, $matches)) {
                    $parsed = json_decode($matches[0], true);
                    if ($parsed && isset($parsed['total'])) {
                        return [
                            'score' => [
                                'fluency' => (int)($parsed['fluency'] ?? 60),
                                'vocabulary' => (int)($parsed['vocabulary'] ?? 60),
                                'grammar' => (int)($parsed['grammar'] ?? 60),
                                'interaction' => (int)($parsed['interaction'] ?? 60),
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
            Log::warning('AI speaking evaluation failed', ['error' => $e->getMessage()]);
        }

        return [];
    }

    private function evaluateSpeaking(string $text, int $wordCount, int $duration): array
    {
        $wpm = $duration > 0 ? round(($wordCount / $duration) * 60) : 0;
        $fluency = match(true) {
            $wpm >= 100 => 85,
            $wpm >= 70  => 70,
            $wpm >= 40  => 55,
            default     => 35,
        };
        $vocabulary = preg_match('/\b(however|although|furthermore|consequently|moreover)\b/i', $text) ? 75 : 55;
        $pronunciation = 65; // cannot evaluate without audio
        $interaction = $wordCount >= 50 ? 70 : 45;
        $total = round(($fluency + $vocabulary + $pronunciation + $interaction) / 4);
        return compact('fluency', 'vocabulary', 'pronunciation', 'interaction', 'total', 'wpm');
    }

    private function buildFeedback(array $score, int $wordCount, int $duration): array
    {
        return [
            'fluency' => $score['fluency'] >= 70 ? 'Good speaking pace.' : 'Try to speak more fluently without long pauses.',
            'vocabulary' => $score['vocabulary'] >= 70 ? 'Good vocabulary range.' : 'Use more advanced vocabulary and connectors.',
            'note' => "Speech rate: ~{$score['wpm']} words/minute. Target: 90-110 wpm for B2.",
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
        $sp->current_score = max(0, min(100, $sp->current_score + ($isCorrect ? 2 : -1)));
        $sp->mastery_level = $sp->getMasteryLevel();
        $sp->last_practiced_at = now();
        $sp->save();

        $profile = B2UserProfile::firstOrCreate(['user_id' => $userId]);
        $profile->speaking_score = $sp->current_score;
        $profile->updateOverallScore();

        $streak = B2UserStreak::firstOrCreate(['user_id' => $userId]);
        $streak->updateStreak();
    }
}
