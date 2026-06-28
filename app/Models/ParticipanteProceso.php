<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParticipanteProceso extends Model
{
    use HasFactory;

    protected $table = 'participantes_proceso';

    protected $fillable = [
        'proceso_id',
        'posicion',
        'nombre_gva',
        'estado',
        'lloc_adjudicado',
        'centro_nombre',
        'localitat',
        'especialidad_codigo',
        'jornada',
        'cambio',
        'cambio_en',
    ];

    protected function casts(): array
    {
        return [
            'posicion' => 'integer',
            'cambio_en' => 'datetime',
        ];
    }

    public function proceso(): BelongsTo
    {
        return $this->belongsTo(Proceso::class);
    }
}
