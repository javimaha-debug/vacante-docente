<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocenteMerito extends Model
{
    protected $table = 'docente_meritos';

    protected $fillable = [
        'user_id', 'tipo', 'titulo', 'organismo', 'horas', 'creditos_ects',
        'fecha_inicio', 'fecha_fin', 'puntos_calculados', 'document_id',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'puntos_calculados' => 'decimal:3',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(UserDocument::class, 'document_id');
    }
}
