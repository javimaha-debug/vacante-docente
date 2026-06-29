<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemaOficial extends Model
{
    protected $table = 'temas_oficiales';

    protected $fillable = [
        'temario_id',
        'numero',
        'titulo',
        'esquema',
        'bibliografia',
        'keywords',
        'tiempo_estimado_minutos',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'esquema' => 'array',
            'bibliografia' => 'array',
            'keywords' => 'array',
            'tiempo_estimado_minutos' => 'integer',
            'generated_at' => 'datetime',
        ];
    }

    public function temario(): BelongsTo
    {
        return $this->belongsTo(TemarioOficial::class, 'temario_id');
    }

    /** Whether this tema has an AI-generated esquema. */
    public function isEnriched(): bool
    {
        return $this->generated_at !== null && ! empty($this->esquema);
    }
}
