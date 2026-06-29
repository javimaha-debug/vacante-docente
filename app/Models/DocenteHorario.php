<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocenteHorario extends Model
{
    protected $table = 'docente_horario';

    protected $fillable = [
        'user_id', 'grupo_id', 'dia_semana', 'hora_inicio', 'hora_fin', 'aula',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(DocenteGrupo::class, 'grupo_id');
    }
}
