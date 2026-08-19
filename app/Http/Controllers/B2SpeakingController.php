<?php
namespace App\Http\Controllers;

use App\Models\B2Exercise;
use App\Models\B2ExerciseHistory;
use App\Models\B2SkillProgress;
use App\Models\B2UserProfile;
use App\Models\B2UserStreak;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $score = $this->evaluateSpeaking($transcription, $wordCount, $duration);
        $isCorrect = $score['total'] >= 60;
        $pointsEarned = $isCorrect ? round(($score['total'] / 100) * $exercise->points_reward) : 0;

        B2ExerciseHistory::create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'skill' => 'speaking',
            'user_answer' => $transcription,
            'is_correct' => $isCorrect,
            'points_earned' => $pointsEarned,
            'time_spent_seconds' => $request->input('time_spent_seconds'),
            'score_percentage' => $score['total'],
        ]);

        $this->updateProgress($user->id, 'speaking', $isCorrect, $pointsEarned);

        return response()->json([
            'score' => $score,
            'is_correct' => $isCorrect,
            'points_earned' => $pointsEarned,
            'word_count' => $wordCount,
            'feedback' => $this->buildFeedback($score, $wordCount, $duration),
        ]);
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
