<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocenteRecursoCompartido extends Model
{
    protected $table = 'docente_recursos_compartidos';

    protected $fillable = [
        'user_id', 'tipo', 'recurso_id', 'moderado',
        'valoracion_media', 'num_valoraciones', 'num_descargas',
    ];

    protected $casts = [
        'moderado' => 'boolean',
        'valoracion_media' => 'decimal:2',
    ];

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function valoraciones(): HasMany
    {
        return $this->hasMany(DocenteRecursoValoracion::class, 'recurso_compartido_id');
    }
}
