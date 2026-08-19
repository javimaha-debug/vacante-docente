<?php
namespace App\Http\Controllers;

use App\Models\B2Exercise;
use App\Models\B2ExerciseHistory;
use App\Models\B2SkillProgress;
use App\Models\B2UserProfile;
use App\Models\B2UserStreak;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class B2ExerciseController extends Controller
{
    private const SKILLS = ['reading', 'grammar', 'writing', 'listening', 'speaking'];

    public function getSkillsOverview(Request $request): JsonResponse
    {
        $user = $request->user();
        $progress = B2SkillProgress::where('user_id', $user->id)->get()->keyBy('skill');
        $exerciseCounts = B2Exercise::where('is_active', true)
            ->selectRaw('skill, COUNT(*) as total')
            ->groupBy('skill')
            ->pluck('total', 'skill');

        $skills = [];
        foreach (self::SKILLS as $skill) {
            $sp = $progress->get($skill);
            $skills[$skill] = [
                'score' => $sp?->current_score ?? 0,
                'mastery_level' => $sp?->mastery_level ?? 'beginner',
                'exercises_completed' => $sp?->exercises_completed ?? 0,
                'accuracy' => $sp?->accuracy_percentage ?? 0,
                'available_exercises' => $exerciseCounts[$skill] ?? 0,
            ];
        }

        return response()->json(['skills' => $skills]);
    }

    public function getExerciseBySkill(Request $request, string $skill): JsonResponse
    {
        if (!in_array($skill, self::SKILLS)) {
            return response()->json(['error' => 'Skill inválido'], 422);
        }

        $user = $request->user();
        $recentIds = B2ExerciseHistory::where('user_id', $user->id)
            ->where('skill', $skill)
            ->where('created_at', '>=', now()->subDays(3))
            ->pluck('exercise_id');

        $exercise = B2Exercise::bySkill($skill)
            ->when($recentIds->isNotEmpty(), fn($q) => $q->whereNotIn('id', $recentIds))
            ->inRandomOrder()
            ->first();

        // fallback: pick any if all recently done
        $exercise ??= B2Exercise::bySkill($skill)->inRandomOrder()->first();

        if (!$exercise) {
            return response()->json(['error' => 'No hay ejercicios disponibles'], 404);
        }

        return response()->json($this->formatExercise($exercise));
    }

    public function getNextExercise(Request $request, string $skill): JsonResponse
    {
        return $this->getExerciseBySkill($request, $skill);
    }

    public function submitExercise(Request $request, int $exerciseId): JsonResponse
    {
        $exercise = B2Exercise::findOrFail($exerciseId);
        $user = $request->user();

        $request->validate([
            'answer' => 'required|string',
            'time_spent_seconds' => 'nullable|integer|min:0',
        ]);

        $userAnswer = trim($request->input('answer'));
        $isCorrect = $this->checkAnswer($exercise, $userAnswer);
        $pointsEarned = $isCorrect ? $exercise->points_reward : 0;

        B2ExerciseHistory::create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'skill' => $exercise->skill,
            'user_answer' => $userAnswer,
            'is_correct' => $isCorrect,
            'points_earned' => $pointsEarned,
            'time_spent_seconds' => $request->input('time_spent_seconds'),
        ]);

        $newScore = $this->updateSkillProgress($user->id, $exercise->skill, $isCorrect, $pointsEarned);
        $this->updateUserProfile($user->id, $exercise->skill, $newScore);
        $this->updateStreak($user->id);

        return response()->json([
            'is_correct' => $isCorrect,
            'points_earned' => $pointsEarned,
            'correct_answer' => $exercise->correct_answer,
            'explanation' => $exercise->explanation,
            'new_score' => $newScore,
        ]);
    }

    private function checkAnswer(B2Exercise $exercise, string $userAnswer): bool
    {
        $correct = $exercise->correct_answer;
        return mb_strtolower(trim($userAnswer)) === mb_strtolower(trim($correct));
    }

    private function updateSkillProgress(int $userId, string $skill, bool $isCorrect, int $points): int
    {
        $sp = B2SkillProgress::firstOrCreate(
            ['user_id' => $userId, 'skill' => $skill],
            ['current_score' => 0, 'exercises_completed' => 0, 'exercises_correct' => 0]
        );

        $sp->exercises_completed++;
        if ($isCorrect) $sp->exercises_correct++;

        $sp->accuracy_percentage = $sp->exercises_completed > 0
            ? round(($sp->exercises_correct / $sp->exercises_completed) * 100, 1)
            : 0;

        // Score: weighted average of accuracy, capped at 100
        $scoreIncrement = $isCorrect ? min(3, $points) : -1;
        $sp->current_score = max(0, min(100, $sp->current_score + $scoreIncrement));
        $sp->mastery_level = $sp->getMasteryLevel();
        $sp->last_practiced_at = now();
        $sp->save();

        return $sp->current_score;
    }

    private function updateUserProfile(int $userId, string $skill, int $skillScore): void
    {
        $profile = B2UserProfile::firstOrCreate(['user_id' => $userId]);
        $column = $skill . '_score';
        $profile->$column = $skillScore;
        $profile->updateOverallScore();
        $profile->last_practiced_at = now();
        $profile->save();
    }

    private function updateStreak(int $userId): void
    {
        $streak = B2UserStreak::firstOrCreate(['user_id' => $userId]);
        $streak->updateStreak();
    }

    private function formatExercise(B2Exercise $exercise): array
    {
        return [
            'id' => $exercise->id,
            'skill' => $exercise->skill,
            'difficulty' => $exercise->difficulty,
            'type' => $exercise->type,
            'question_text' => $exercise->question_text,
            'options' => $exercise->options,
            'user_input_instruction' => $exercise->user_input_instruction,
            'points_reward' => $exercise->points_reward,
            'category' => $exercise->category,
        ];
    }
}
