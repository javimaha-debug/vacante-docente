<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2MockExam extends Model
{
    protected $table = 'b2_mock_exams';

    protected $fillable = [
        'user_id',
        'status',
        'total_duration_minutes',
        'started_at',
        'completed_at',
        'reading_score',
        'writing_score',
        'listening_score',
        'total_score',
        'grade',
        'section_answers',
        'section_results',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'section_answers' => 'array',
        'section_results' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Cambridge scale: 160-190 = B2 range
    // Grade: A(180+), B(170+), C(160+), D(140+), U(<140)
    public static function calculateGrade(int $totalScore): string
    {
        if ($totalScore >= 180) return 'A';
        if ($totalScore >= 170) return 'B';
        if ($totalScore >= 160) return 'C';
        if ($totalScore >= 140) return 'D';
        return 'U';
    }

    public function getCambridgeLevel(): string
    {
        $score = $this->total_score ?? 0;
        if ($score >= 200) return 'C2';
        if ($score >= 180) return 'C1';
        if ($score >= 160) return 'B2';
        if ($score >= 140) return 'B1';
        return 'A2';
    }
}
