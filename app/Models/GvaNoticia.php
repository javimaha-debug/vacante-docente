<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GvaNoticia extends Model
{
    use HasFactory;

    protected $table = 'gva_noticias';

    protected $fillable = [
        'titulo',
        'url',
        'fecha_publicacion',
        'tipo',
        'resumen',
        'keywords_matched',
        'notificado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_publicacion' => 'date',
            'keywords_matched' => 'array',
            'notificado' => 'boolean',
        ];
    }
}
