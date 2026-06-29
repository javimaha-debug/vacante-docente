<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OposicionSesion extends Model
{
    protected $table = 'oposicion_sesiones';

    // Sessions only carry a creation timestamp (no updated_at column).
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'fecha',
        'minutos',
        'temas_estudiados',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'minutos' => 'integer',
            'temas_estudiados' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
