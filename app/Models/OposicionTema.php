<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OposicionTema extends Model
{
    protected $table = 'oposicion_temas';

    protected $fillable = [
        'user_id',
        'especialidad_code',
        'numero',
        'titulo',
        'status',
        'notas',
        'last_studied_at',
        'score',
        'score_sessions',
        'score_updated_at',
        'score_breakdown',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'last_studied_at' => 'datetime',
            'score' => 'integer',
            'score_sessions' => 'integer',
            'score_updated_at' => 'datetime',
            'score_breakdown' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
