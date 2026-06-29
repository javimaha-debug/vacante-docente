<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurriculoContenido extends Model
{
    protected $fillable = [
        'etapa', 'asignatura', 'curso', 'bloque', 'contenido',
        'competencias_clave', 'criterios_evaluacion',
        'fuente', 'comunidad_autonoma', 'real_decreto',
    ];

    protected $casts = [
        'competencias_clave' => 'array',
        'criterios_evaluacion' => 'array',
    ];
}
