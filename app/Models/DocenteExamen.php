<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocenteExamen extends Model
{
    protected $table = 'docente_examenes';

    protected $fillable = [
        'user_id', 'asignatura_id', 'unidad_id', 'titulo', 'tipo',
        'tiempo_minutos', 'instrucciones', 'preguntas',
    ];

    protected $casts = [
        'preguntas' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
