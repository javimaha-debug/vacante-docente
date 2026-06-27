<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CentroValoracion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'centro_valoraciones';

    protected $fillable = [
        'centro_id',
        'user_id',
        'puntuacion',
        'ambiente_trabajo',
        'equipo_directivo',
        'instalaciones',
        'comentario',
        'es_anonima',
        'curso_escolar',
    ];

    protected function casts(): array
    {
        return [
            'puntuacion' => 'integer',
            'ambiente_trabajo' => 'integer',
            'equipo_directivo' => 'integer',
            'instalaciones' => 'integer',
            'es_anonima' => 'boolean',
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
