<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OposicionEspecialidad extends Model
{
    protected $table = 'oposicion_especialidades';

    protected $fillable = [
        'user_id',
        'especialidad_code',
        'cuerpo',
        'comunidad_autonoma',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
