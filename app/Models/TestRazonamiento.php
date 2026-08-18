<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestRazonamiento extends Model
{
    protected $table = 'test_razonamiento';

    protected $fillable = [
        'tipo',
        'pregunta',
        'opciones',
        'respuesta_correcta',
        'tiempo_esperado_segundos',
        'explicacion',
        'dificultad',
        'tipo_error',
    ];

    protected $casts = [
        'opciones' => 'array',
    ];

    public function scopePorTipo($query, $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    public function scopePorDificultad($query, $dificultad)
    {
        return $query->where('dificultad', $dificultad);
    }

    public static function aleatorio($tipo = null)
    {
        $query = self::query();
        if ($tipo) {
            $query = $query->porTipo($tipo);
        }
        return $query->inRandomOrder()->first();
    }
}
