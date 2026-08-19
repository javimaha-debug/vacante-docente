<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2UserStreak extends Model
{
    protected $table = 'b2_user_streaks';
    protected $fillable = [
        'user_id', 'current_streak_days', 'longest_streak_days',
        'last_practiced_date', 'streak_reset_at',
    ];
    protected $casts = [
        'last_practiced_date' => 'date',
        'streak_reset_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function updateStreak(): void
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        $last = $this->last_practiced_date?->toDateString();

        if ($last === $today) return;

        if ($last === $yesterday) {
            $this->current_streak_days++;
        } else {
            $this->current_streak_days = 1;
            $this->streak_reset_at = now();
        }

        if ($this->current_streak_days > $this->longest_streak_days) {
            $this->longest_streak_days = $this->current_streak_days;
        }

        $this->last_practiced_date = $today;
        $this->save();
    }
}
