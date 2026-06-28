<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdjudicacionContinua extends Model
{
    protected $table = 'adjudicaciones_continuas';

    protected $fillable = [
        'curso',
        'fecha',
        'cuerpo',
        'nombre_gva',
        'especialidad_codigo',
        'posicion',
        'estado',
        'lloc_adjudicado',
        'centro_codigo',
        'centro_nombre',
        'localitat',
        'jornada',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'posicion' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
