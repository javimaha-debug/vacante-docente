<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'importado_en',
        'import_estado',
        'import_resumen',
        'proceso_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_publicacion' => 'date',
            'keywords_matched' => 'array',
            'notificado' => 'boolean',
            'importado_en' => 'datetime',
        ];
    }

    public function proceso(): BelongsTo
    {
        return $this->belongsTo(Proceso::class);
    }
}
