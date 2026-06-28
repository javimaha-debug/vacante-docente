<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $table = 'planes';

    protected $fillable = [
        'codigo', 'nombre', 'descripcion', 'precio_mensual', 'precio_anual', 'precio_temporada',
        'stripe_price_id_mensual', 'stripe_price_id_anual', 'activo', 'features', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'activo' => 'boolean',
            'precio_mensual' => 'decimal:2',
            'precio_anual' => 'decimal:2',
            'precio_temporada' => 'decimal:2',
        ];
    }
}
