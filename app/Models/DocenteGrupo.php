<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocenteGrupo extends Model
{
    protected $table = 'docente_grupos';

    protected $fillable = ['user_id', 'asignatura_id', 'nombre', 'num_alumnos', 'color'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function asignatura(): BelongsTo
    {
        return $this->belongsTo(DocenteAsignatura::class, 'asignatura_id');
    }

    public function sesiones(): HasMany
    {
        return $this->hasMany(DocenteSesion::class, 'grupo_id');
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(DocenteHorario::class, 'grupo_id');
    }
}
