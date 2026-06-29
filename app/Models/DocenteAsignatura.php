<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocenteAsignatura extends Model
{
    protected $table = 'docente_asignaturas';

    protected $fillable = [
        'user_id', 'nombre', 'codigo_asignatura', 'etapa', 'curso', 'año_academico',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grupos(): HasMany
    {
        return $this->hasMany(DocenteGrupo::class, 'asignatura_id');
    }

    public function programaciones(): HasMany
    {
        return $this->hasMany(DocenteProgramacion::class, 'asignatura_id');
    }
}
