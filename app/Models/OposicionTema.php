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
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'last_studied_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
