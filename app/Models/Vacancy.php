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
        'cambio',
        'cambio_en',
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

    /** The centre this vacancy belongs to (matched by GVA code). */
    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class, 'centro_codigo', 'codigo');
    }

    /**
     * Filter by an explorer "etiqueta". CRA / Centre singular come from the
     * centre's ANPE characteristics (joined by centro_codigo); req. lingüístic
     * is a column; the rest match the vacancy's observ_tags JSON. Matching uses
     * portable LIKE on the JSON text so it works on PostgreSQL and SQLite.
     */
    public function scopeWithTag($query, string $tag)
    {
        return match ($tag) {
            'Req. lingüístico', 'req_ling' => $query->where(
                fn ($q) => $q->where('req_ling', true)->orWhere('requisito_linguistico', true)
            ),
            'CRA' => $this->scopeInCentreWith($query, 'CRA'),
            'Centre singular' => $this->scopeInCentreWith($query, 'SINGULAR'),
            'Difícil provisión' => $query->where(
                fn ($q) => $q->where('observ', 'like', '%dif%provisi%')->orWhere('observaciones', 'like', '%dif%provisi%')
            ),
            default => $query->where('observ_tags', 'like', '%"'.$tag.'"%'),
        };
    }

    /** Vacancies whose centre carries the given ANPE characteristic. */
    private function scopeInCentreWith($query, string $caracteristica)
    {
        return $query->whereIn('centro_codigo', function ($sub) use ($caracteristica) {
            $sub->select('codigo')->from('centros')
                ->where('caracteristicas', 'like', '%"'.$caracteristica.'"%');
        });
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
