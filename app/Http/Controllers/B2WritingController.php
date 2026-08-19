<?php
namespace App\Http\Controllers;

use App\Models\B2Exercise;
use App\Models\B2ExerciseHistory;
use App\Models\B2SkillProgress;
use App\Models\B2UserProfile;
use App\Models\B2UserStreak;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        // Heuristic evaluation: check word count and basic criteria
        $score = $this->evaluateWriting($userText, $wordCount, $exercise);
        $isCorrect = $score['total'] >= 60;
        $pointsEarned = $isCorrect ? round(($score['total'] / 100) * $exercise->points_reward) : 0;

        $feedback = $this->buildFeedback($score, $wordCount);

        B2ExerciseHistory::create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'skill' => 'writing',
            'user_answer' => $userText,
            'is_correct' => $isCorrect,
            'points_earned' => $pointsEarned,
            'feedback_ia' => json_encode($feedback),
            'time_spent_seconds' => $request->input('time_spent_seconds'),
            'score_percentage' => $score['total'],
        ]);

        $this->updateProgress($user->id, 'writing', $isCorrect, $pointsEarned);

        return response()->json([
            'score' => $score,
            'is_correct' => $isCorrect,
            'points_earned' => $pointsEarned,
            'feedback' => $feedback,
            'word_count' => $wordCount,
        ]);
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
