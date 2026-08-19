<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2UserProfile extends Model
{
    protected $table = 'b2_user_profile';
    protected $fillable = [
        'user_id', 'reading_score', 'grammar_score', 'writing_score',
        'listening_score', 'speaking_score', 'overall_score', 'diagnostic_band',
        'diagnostic_completed_at', 'last_practiced_at',
    ];
    protected $casts = [
        'diagnostic_completed_at' => 'datetime',
        'last_practiced_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function updateOverallScore(): void
    {
        $this->overall_score = round((
            $this->reading_score +
            $this->grammar_score +
            $this->writing_score +
            $this->listening_score +
            $this->speaking_score
        ) / 5);
        $this->save();
    }

    public function getSkillsArray(): array
    {
        return [
            'reading' => $this->reading_score,
            'grammar' => $this->grammar_score,
            'writing' => $this->writing_score,
            'listening' => $this->listening_score,
            'speaking' => $this->speaking_score,
        ];
    }
}
