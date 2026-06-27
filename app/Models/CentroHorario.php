<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CentroHorario extends Model
{
    use HasFactory;

    protected $table = 'centro_horarios';

    protected $fillable = [
        'centro_id',
        'user_id',
        'hora_entrada',
        'hora_salida',
        'hora_entrada_tarde',
        'hora_salida_tarde',
        'jornada_continua',
        'dia_libre',
        'curso_escolar',
        'validaciones',
        'validado_por',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'jornada_continua' => 'boolean',
            'validaciones' => 'integer',
            'validado_por' => 'array',
        ];
    }

    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
