<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SesionTest extends Model
{
    protected $table = 'sesion_test';

    protected $fillable = [
        'user_id',
        'tipo_razonamiento',
        'fecha_inicio',
        'fecha_fin',
        'preguntas_respondidas',
        'correctas',
        'tiempo_total_segundos',
        'tiempo_promedio_pregunta',
        'errores',
        'score',
    ];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_fin'    => 'datetime',
        'errores'      => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
