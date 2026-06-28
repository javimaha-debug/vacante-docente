<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Centro extends Model
{
    use HasFactory;

    protected $table = 'centros';

    protected $fillable = [
        'ccaa_id',
        'codigo',
        'nombre',
        'tipo',
        'localidad',
        'provincia',
        'direccion',
        'direccion_oficial',
        'telefono',
        'email',
        'web',
        'latitude',
        'longitude',
        'etapas',
        'lineas',
        'bilingue',
        'datos_verificados',
        'fuente',
        'caracteristicas',
    ];

    protected function casts(): array
    {
        return [
            'etapas' => 'array',
            'caracteristicas' => 'array',
            'lineas' => 'integer',
            'bilingue' => 'boolean',
            'datos_verificados' => 'boolean',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    public function ccaa(): BelongsTo
    {
        return $this->belongsTo(Ccaa::class);
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(CentroHorario::class);
    }

    public function valoraciones(): HasMany
    {
        return $this->hasMany(CentroValoracion::class);
    }
}
