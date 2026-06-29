<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocenteRecursoValoracion extends Model
{
    protected $table = 'docente_recurso_valoraciones';
    public $timestamps = false;

    protected $fillable = ['user_id', 'recurso_compartido_id', 'puntuacion', 'comentario'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function recurso(): BelongsTo
    {
        return $this->belongsTo(DocenteRecursoCompartido::class, 'recurso_compartido_id');
    }
}
