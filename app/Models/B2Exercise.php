<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class B2Exercise extends Model
{
    protected $table = 'b2_exercises';
    protected $fillable = [
        'skill', 'difficulty', 'type', 'question_text', 'options',
        'user_input_instruction', 'correct_answer', 'explanation',
        'points_reward', 'category', 'source', 'is_active',
    ];
    protected $casts = ['options' => 'array'];

    public function scopeBySkill($query, string $skill)
    {
        return $query->where('skill', $skill)->where('is_active', true);
    }

    public function scopeByDifficulty($query, int $difficulty)
    {
        return $query->where('difficulty', $difficulty);
    }

    public static function getRandomBySkill(string $skill, int $count = 1)
    {
        return self::bySkill($skill)->inRandomOrder()->take($count)->get();
    }

    public function history(): HasMany
    {
        return $this->hasMany(B2ExerciseHistory::class, 'exercise_id');
    }
}
