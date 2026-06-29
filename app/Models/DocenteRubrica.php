<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocenteRubrica extends Model
{
    protected $table = 'docente_rubricas';

    protected $fillable = [
        'user_id', 'asignatura_id', 'titulo', 'descripcion', 'tipo_tarea',
        'etapa', 'criterios', 'es_publica', 'veces_usada',
    ];

    protected $casts = [
        'criterios' => 'array',
        'es_publica' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
