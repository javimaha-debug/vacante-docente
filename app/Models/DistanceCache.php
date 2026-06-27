<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistanceCache extends Model
{
    use HasFactory;

    protected $table = 'distance_cache';

    public const UPDATED_AT = null;

    public const CREATED_AT = null;

    protected $fillable = [
        'vacancy_id',
        'home_lat',
        'home_lng',
        'mode',
        'duration_minutes',
        'distance_km',
        'traffic_note',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'home_lat' => 'decimal:7',
            'home_lng' => 'decimal:7',
            'duration_minutes' => 'integer',
            'distance_km' => 'decimal:2',
            'calculated_at' => 'datetime',
        ];
    }

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(Vacancy::class);
    }
}
