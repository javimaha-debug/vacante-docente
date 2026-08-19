<?php
namespace App\Http\Controllers;

use App\Models\B2UserProfile;
use App\Models\B2SkillProgress;
use App\Models\B2ExerciseHistory;
use App\Models\B2UserStreak;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class B2DashboardController extends Controller
{
    public function getDashboardData(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = B2UserProfile::firstOrCreate(['user_id' => $user->id], [
            'reading_score' => 0, 'grammar_score' => 0, 'writing_score' => 0,
            'listening_score' => 0, 'speaking_score' => 0, 'overall_score' => 0,
        ]);

        $skillsProgress = B2SkillProgress::where('user_id', $user->id)->get()
            ->keyBy('skill');

        $streak = B2UserStreak::firstOrCreate(['user_id' => $user->id]);

        $skills = [];
        foreach (['reading', 'grammar', 'writing', 'listening', 'speaking'] as $skill) {
            $sp = $skillsProgress->get($skill);
            $skills[$skill] = [
                'score' => $sp?->current_score ?? 0,
                'exercises_completed' => $sp?->exercises_completed ?? 0,
                'exercises_correct' => $sp?->exercises_correct ?? 0,
                'accuracy' => $sp?->accuracy_percentage ?? 0,
                'mastery_level' => $sp?->mastery_level ?? 'beginner',
                'last_practiced_at' => $sp?->last_practiced_at,
            ];
        }

        return response()->json([
            'profile' => [
                'overall_score' => $profile->overall_score,
                'diagnostic_band' => $profile->diagnostic_band,
                'diagnostic_completed_at' => $profile->diagnostic_completed_at,
            ],
            'skills' => $skills,
            'streak' => [
                'current' => $streak->current_streak_days,
                'longest' => $streak->longest_streak_days,
                'last_practiced' => $streak->last_practiced_date,
            ],
        ]);
    }

    public function getWeeklyStats(Request $request): JsonResponse
    {
        $user = $request->user();
        $since = now()->subDays(7);

        $history = B2ExerciseHistory::where('user_id', $user->id)
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as date, skill, COUNT(*) as total, SUM(is_correct::int) as correct, SUM(points_earned) as points')
            ->groupBy('date', 'skill')
            ->orderBy('date')
            ->get();

        return response()->json(['weekly_stats' => $history]);
    }
}
