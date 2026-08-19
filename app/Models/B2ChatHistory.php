<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2ChatHistory extends Model
{
    protected $table = 'b2_chat_history';

    protected $fillable = [
        'user_id',
        'exercise_history_id',
        'skill',
        'role',
        'content',
        'context_type',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exerciseHistory(): BelongsTo
    {
        return $this->belongsTo(B2ExerciseHistory::class, 'exercise_history_id');
    }

    public static function getConversationContext(int $userId, ?int $historyId = null, int $limit = 10): array
    {
        $query = static::where('user_id', $userId);
        if ($historyId) {
            $query->where('exercise_history_id', $historyId);
        }
        return $query->latest()->limit($limit)->get()->reverse()->values()
            ->map(fn($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();
    }
}
