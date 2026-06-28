<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParticipanteImportacion extends Model
{
    protected $table = 'participante_importaciones';

    protected $fillable = [
        'proceso_id',
        'importado_en',
        'total',
        'nuevos',
        'modificados',
        'eliminados',
        'es_primera',
    ];

    protected function casts(): array
    {
        return [
            'importado_en' => 'datetime',
            'es_primera' => 'boolean',
        ];
    }

    public function proceso(): BelongsTo
    {
        return $this->belongsTo(Proceso::class);
    }
}
