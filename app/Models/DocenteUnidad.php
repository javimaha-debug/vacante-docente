<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocenteUnidad extends Model
{
    protected $table = 'docente_unidades';

    protected $fillable = [
        'programacion_id', 'user_id', 'numero', 'titulo', 'tipo',
        'descripcion', 'competencias', 'criterios_evaluacion',
        'num_sesiones_previstas', 'trimestre', 'tema_oficial_id',
    ];

    protected $casts = [
        'competencias' => 'array',
        'criterios_evaluacion' => 'array',
    ];

    public function programacion(): BelongsTo
    {
        return $this->belongsTo(DocenteProgramacion::class, 'programacion_id');
    }

    public function sesiones(): HasMany
    {
        return $this->hasMany(DocenteSesion::class, 'unidad_id');
    }
}
