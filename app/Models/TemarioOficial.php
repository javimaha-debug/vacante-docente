<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TemarioOficial extends Model
{
    protected $table = 'temarios_oficiales';

    protected $fillable = [
        'cuerpo',
        'especialidad_code',
        'especialidad_nombre',
        'comunidad_autonoma',
        'source_url',
        'source_order',
        'total_temas',
        'published_at',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'total_temas' => 'integer',
            'published_at' => 'date',
            'last_synced_at' => 'datetime',
        ];
    }

    public function temas(): HasMany
    {
        return $this->hasMany(TemaOficial::class, 'temario_id')->orderBy('numero');
    }
}
