<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vacancy extends Model
{
    use HasFactory;

    protected $fillable = [
        'specialty_id',
        'num',
        'provincia',
        'localidad',
        'centro_codigo',
        'centro_nombre',
        'tipo_centro',
        'lloc',
        'req_ling',
        'observ',
        'observ_tags',
        'year',
        'proceso_id',
        'ccaa_id',
        'num_orden',
        'codi_centre',
        'tipo_jornada',
        'requisito_linguistico',
        'itinerante',
        'observaciones',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'req_ling' => 'boolean',
            'observ_tags' => 'array',
            'num' => 'integer',
            'year' => 'integer',
            'num_orden' => 'integer',
            'requisito_linguistico' => 'boolean',
            'itinerante' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function proceso(): BelongsTo
    {
        return $this->belongsTo(Proceso::class);
    }

    public function ccaa(): BelongsTo
    {
        return $this->belongsTo(Ccaa::class);
    }

    public function preferences(): HasMany
    {
        return $this->hasMany(UserVacancyPreference::class);
    }

    public function distanceCaches(): HasMany
    {
        return $this->hasMany(DistanceCache::class);
    }
}
