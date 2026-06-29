<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocenteSituacionAprendizaje extends Model
{
    protected $table = 'docente_situaciones_aprendizaje';

    protected $fillable = [
        'user_id', 'asignatura_id', 'titulo', 'descripcion', 'contexto',
        'competencias_clave', 'competencias_especificas', 'saberes_basicos',
        'actividades', 'criterios_evaluacion',
        'etapa', 'curso', 'asignatura', 'es_publica', 'veces_usada',
    ];

    protected $casts = [
        'competencias_clave' => 'array',
        'competencias_especificas' => 'array',
        'saberes_basicos' => 'array',
        'actividades' => 'array',
        'criterios_evaluacion' => 'array',
        'es_publica' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
