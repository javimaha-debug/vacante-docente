<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocenteProgramacion extends Model
{
    protected $table = 'docente_programaciones';

    protected $fillable = [
        'user_id', 'asignatura_id', 'titulo', 'año_academico',
        'centro_nombre', 'centro_tipo', 'es_bilingue',
        'objetivos_generales', 'metodologia', 'atencion_diversidad',
        'criterios_evaluacion', 'instrumentos_evaluacion',
        'status', 'document_id',
    ];

    protected $casts = [
        'es_bilingue' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function asignatura(): BelongsTo
    {
        return $this->belongsTo(DocenteAsignatura::class, 'asignatura_id');
    }

    public function unidades(): HasMany
    {
        return $this->hasMany(DocenteUnidad::class, 'programacion_id')->orderBy('numero');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(UserDocument::class, 'document_id');
    }
}
