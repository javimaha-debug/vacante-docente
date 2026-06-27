<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserHistorial extends Model
{
    use HasFactory;

    protected $table = 'user_historial';

    protected $fillable = [
        'user_id',
        'specialty_id',
        'proceso_id',
        'anyo',
        'posicion_provisional',
        'posicion_definitiva',
        'estado',
        'centro_adjudicado_id',
        'lloc_adjudicado',
        'jornada_adjudicada',
        'fecha_adjudicacion',
    ];

    protected function casts(): array
    {
        return [
            'anyo' => 'integer',
            'posicion_provisional' => 'integer',
            'posicion_definitiva' => 'integer',
            'fecha_adjudicacion' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function proceso(): BelongsTo
    {
        return $this->belongsTo(Proceso::class);
    }

    public function centroAdjudicado(): BelongsTo
    {
        return $this->belongsTo(Centro::class, 'centro_adjudicado_id');
    }
}
