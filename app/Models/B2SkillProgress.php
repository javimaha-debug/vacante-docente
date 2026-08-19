<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2SkillProgress extends Model
{
    protected $table = 'b2_skill_progress';
    protected $fillable = [
        'user_id', 'skill', 'current_score', 'exercises_completed',
        'exercises_correct', 'accuracy_percentage', 'mastery_level',
        'last_practiced_at',
    ];
    protected $casts = ['last_practiced_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getMasteryLevel(): string
    {
        if ($this->current_score >= 90) return 'mastered';
        if ($this->current_score >= 75) return 'advanced';
        if ($this->current_score >= 50) return 'intermediate';
        return 'beginner';
    }
}
