<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2ExerciseHistory extends Model
{
    protected $table = 'b2_exercise_history';
    protected $fillable = [
        'user_id', 'exercise_id', 'skill', 'user_answer', 'is_correct',
        'points_earned', 'feedback_ia', 'time_spent_seconds', 'score_percentage',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(B2Exercise::class);
    }
}
