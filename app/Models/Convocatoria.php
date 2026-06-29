<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Convocatoria extends Model
{
    protected $table = 'convocatorias';

    protected $fillable = [
        'titulo',
        'comunidad_autonoma',
        'cuerpo',
        'especialidades',
        'estado',
        'pendiente_revision',
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
            'pendiente_revision' => 'boolean',
        ];
    }

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(DetectedDocument::class, 'source_document_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(ConvocatoriaAlert::class);
    }
}
