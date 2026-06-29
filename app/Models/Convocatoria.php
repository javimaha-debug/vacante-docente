<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Convocatoria extends Model
{
    protected $table = 'convocatorias';

    protected $fillable = [
        'titulo',
        'comunidad_autonoma',
        'cuerpo',
        'especialidades',
        'estado',
        'fecha_estimada',
        'fecha_oficial',
        'url_oficial',
        'boe_url',
        'notas',
        'source_document_id',
    ];

    protected function casts(): array
    {
        return [
            'especialidades' => 'array',
            'fecha_estimada' => 'date',
            'fecha_oficial' => 'date',
        ];
    }

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(DetectedDocument::class, 'source_document_id');
    }
}
