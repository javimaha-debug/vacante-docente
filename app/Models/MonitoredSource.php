<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonitoredSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'url',
        'type',
        'specialty',
        'active',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DetectedDocument::class, 'source_id');
    }
}
