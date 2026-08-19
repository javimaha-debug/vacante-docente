<?php

namespace App\Http\Controllers;

use App\Models\B2ExerciseHistory;
use App\Models\B2SkillProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class B2AnalyticsController extends Controller
{
    public function getErrorAnalysis()
    {
        $user = Auth::user();

        $history = B2ExerciseHistory::where('user_id', $user->id)
            ->where('is_correct', false)
            ->with('exercise:id,skill,category,type,question_text')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        // Group by skill
        $bySkill = $history->groupBy('skill')->map(fn($rows) => [
            'count' => $rows->count(),
            'categories' => $rows->groupBy(fn($r) => $r->exercise?->category ?? 'unknown')
                ->map->count()
                ->sortDesc()
                ->take(5),
        ]);

        // Top error categories globally
        $topCategories = $history
            ->groupBy(fn($r) => $r->exercise?->category ?? 'unknown')
            ->map(fn($rows) => ['category' => $rows->first()->exercise?->category ?? 'unknown', 'count' => $rows->count(), 'skill' => $rows->first()->skill])
            ->sortByDesc('count')
            ->take(8)
            ->values();

        return response()->json([
            'total_errors' => $history->count(),
            'by_skill' => $bySkill,
            'top_error_categories' => $topCategories,
        ]);
    }

    public function getScoreTrend(Request $request)
    {
        $user = Auth::user();
        $days = (int)$request->query('days', 30);
        $days = min($days, 90);

        $rows = B2ExerciseHistory::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays($days))
            ->select('skill', DB::raw("date(created_at) as date"),
                DB::raw("ROUND(AVG(CAST(is_correct AS INTEGER)) * 100, 0) as accuracy"),
                DB::raw('COUNT(*) as total'))
            ->groupBy('skill', DB::raw("date(created_at)"))
            ->orderBy('date')
            ->get();

        // Fill missing days with null for charting
        $skillData = [];
        foreach ($rows as $row) {
            $skillData[$row->skill][] = [
                'date' => $row->date,
                'accuracy' => (int)$row->accuracy,
                'total' => (int)$row->total,
            ];
        }

        return response()->json([
            'days' => $days,
            'trend' => $skillData,
        ]);
    }

    public function getAccuracyByCategory()
    {
        $user = Auth::user();

        $rows = B2ExerciseHistory::where('user_id', $user->id)
            ->join('b2_exercises', 'b2_exercise_history.exercise_id', '=', 'b2_exercises.id')
            ->select(
                'b2_exercises.skill',
                'b2_exercises.category',
                DB::raw("ROUND(AVG(CAST(b2_exercise_history.is_correct AS INTEGER)) * 100, 0) as accuracy"),
                DB::raw('COUNT(*) as attempts')
            )
            ->groupBy('b2_exercises.skill', 'b2_exercises.category')
            ->orderBy('accuracy')
            ->get();

        return response()->json([
            'accuracy_by_category' => $rows,
            'weak_areas' => $rows->where('accuracy', '<', 60)->values(),
            'strong_areas' => $rows->where('accuracy', '>=', 80)->values(),
        ]);
    }

    public function getStudyHabits()
    {
        $user = Auth::user();

        $last30 = B2ExerciseHistory::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->select(
                DB::raw("date(created_at) as date"),
                DB::raw('COUNT(*) as exercises'),
                DB::raw('SUM(CAST(is_correct AS INTEGER)) as correct')
            )
            ->groupBy(DB::raw("date(created_at)"))
            ->orderBy('date')
            ->get();

        $activeDays = $last30->count();
        $avgPerDay = $activeDays > 0 ? round($last30->avg('exercises'), 1) : 0;
        $bestDay = $last30->sortByDesc('exercises')->first();

        return response()->json([
            'active_days_last_30' => $activeDays,
            'avg_exercises_per_day' => $avgPerDay,
            'best_day' => $bestDay ? ['date' => $bestDay->date, 'exercises' => $bestDay->exercises] : null,
            'daily_data' => $last30,
        ]);
    }
}
