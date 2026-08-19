<?php
namespace App\Http\Controllers;

use App\Models\B2Achievement;
use App\Models\B2ExerciseHistory;
use App\Models\B2SkillProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class B2AchievementController extends Controller
{
    public function getUserAchievements(Request $request): JsonResponse
    {
        $achievements = B2Achievement::where('user_id', $request->user()->id)
            ->orderByDesc('unlocked_at')
            ->get();

        return response()->json(['achievements' => $achievements]);
    }

    public function checkAndUnlock(int $userId): array
    {
        $unlocked = [];
        $stats = B2ExerciseHistory::where('user_id', $userId)
            ->selectRaw('skill, COUNT(*) as total, SUM(CASE WHEN is_correct THEN 1 ELSE 0 END) as correct')
            ->groupBy('skill')
            ->get()->keyBy('skill');

        $badges = $this->badgeDefinitions();

        foreach ($badges as $badge) {
            if (B2Achievement::where('user_id', $userId)->where('badge_id', $badge['id'])->exists()) {
                continue;
            }
            if ($badge['check']($stats, $userId)) {
                B2Achievement::create([
                    'user_id' => $userId,
                    'badge_id' => $badge['id'],
                    'badge_name' => $badge['name'],
                    'badge_description' => $badge['description'],
                    'icon_emoji' => $badge['icon'],
                    'unlocked_at' => now(),
                ]);
                $unlocked[] = $badge;
            }
        }

        return $unlocked;
    }

    private function badgeDefinitions(): array
    {
        return [
            [
                'id' => 'first_exercise',
                'name' => 'First Step',
                'description' => 'Completed your first exercise',
                'icon' => '🥉',
                'check' => fn($stats) => $stats->sum('total') >= 1,
            ],
            [
                'id' => 'grammar_10',
                'name' => 'Grammar Starter',
                'description' => 'Completed 10 grammar exercises',
                'icon' => '📝',
                'check' => fn($stats) => ($stats->get('grammar')?->total ?? 0) >= 10,
            ],
            [
                'id' => 'perfect_session',
                'name' => 'Perfect Session',
                'description' => 'Got 5 exercises correct in a row',
                'icon' => '⭐',
                'check' => fn($stats, $userId) => $this->hasPerfectStreak($userId, 5),
            ],
            [
                'id' => 'reading_king',
                'name' => 'Reading King',
                'description' => 'Completed 20 reading exercises',
                'icon' => '📚',
                'check' => fn($stats) => ($stats->get('reading')?->total ?? 0) >= 20,
            ],
        ];
    }

    private function hasPerfectStreak(int $userId, int $count): bool
    {
        $recent = B2ExerciseHistory::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit($count)
            ->pluck('is_correct');

        return $recent->count() >= $count && $recent->every(fn($v) => $v);
    }
}
