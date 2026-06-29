<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocenteSesion extends Model
{
    protected $table = 'docente_sesiones';

    protected $fillable = [
        'user_id', 'grupo_id', 'unidad_id', 'fecha',
        'titulo_planificado', 'contenido_real', 'impartida', 'impartida_at', 'observaciones',
    ];

    protected $casts = [
        'fecha' => 'date',
        'impartida' => 'boolean',
        'impartida_at' => 'datetime',
    ];

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(DocenteGrupo::class, 'grupo_id');
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(DocenteUnidad::class, 'unidad_id');
    }
}
