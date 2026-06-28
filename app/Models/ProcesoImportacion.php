<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcesoImportacion extends Model
{
    protected $table = 'proceso_importaciones';

    protected $fillable = [
        'proceso_id', 'importado_en', 'total', 'nuevas', 'modificadas', 'eliminadas', 'es_primera',
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
