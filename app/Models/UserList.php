<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserList extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_token',
        'specialty_id',
        'home_address',
        'home_lat',
        'home_lng',
    ];

    protected function casts(): array
    {
        return [
            'home_lat' => 'decimal:7',
            'home_lng' => 'decimal:7',
        ];
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function preferences(): HasMany
    {
        return $this->hasMany(UserVacancyPreference::class);
    }

    /**
     * Whether the list has a geocoded home location.
     */
    public function hasHome(): bool
    {
        return ! is_null($this->home_lat) && ! is_null($this->home_lng);
    }
}
