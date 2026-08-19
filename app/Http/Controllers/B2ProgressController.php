<?php
namespace App\Http\Controllers;

use App\Models\B2ExerciseHistory;
use App\Models\B2SkillProgress;
use App\Models\B2UserStreak;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class B2ProgressController extends Controller
{
    public function getSkillProgress(Request $request, string $skill): JsonResponse
    {
        $sp = B2SkillProgress::where('user_id', $request->user()->id)
            ->where('skill', $skill)
            ->first();

        return response()->json([
            'skill' => $skill,
            'current_score' => $sp?->current_score ?? 0,
            'exercises_completed' => $sp?->exercises_completed ?? 0,
            'exercises_correct' => $sp?->exercises_correct ?? 0,
            'accuracy_percentage' => $sp?->accuracy_percentage ?? 0,
            'mastery_level' => $sp?->mastery_level ?? 'beginner',
            'last_practiced_at' => $sp?->last_practiced_at,
        ]);
    }

    public function getExerciseHistory(Request $request): JsonResponse
    {
        $history = B2ExerciseHistory::where('user_id', $request->user()->id)
            ->with('exercise:id,skill,type,category,question_text')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json(['history' => $history]);
    }

    public function getCurrentStreak(Request $request): JsonResponse
    {
        $streak = B2UserStreak::firstOrCreate(['user_id' => $request->user()->id]);

        return response()->json([
            'current_streak_days' => $streak->current_streak_days,
            'longest_streak_days' => $streak->longest_streak_days,
            'last_practiced_date' => $streak->last_practiced_date,
        ]);
    }
}
