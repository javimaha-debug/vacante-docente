<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlashcardUe extends Model
{
    protected $table = 'flashcard_ue';

    protected $fillable = [
        'categoria',
        'frente',
        'reverso',
        'dificultad',
        'repeticiones',
        'fecha_proxima_repaso',
    ];

    protected $casts = [
        'fecha_proxima_repaso' => 'datetime',
    ];

    public function scopeProximas($query)
    {
        return $query->where('fecha_proxima_repaso', '<=', now());
    }

    public function scopePorCategoria($query, $categoria)
    {
        return $query->where('categoria', $categoria);
    }
}
