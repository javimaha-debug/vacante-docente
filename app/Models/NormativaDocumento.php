<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NormativaDocumento extends Model
{
    protected $table = 'normativa_documentos';

    protected $fillable = [
        'titulo',
        'descripcion',
        'categoria',
        'comunidad_autonoma',
        'especialidad_code',
        'cuerpo',
        'url_oficial',
        'pdf_path',
        'fecha_publicacion',
        'vigente',
        'published_by',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha_publicacion' => 'date',
            'vigente' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
