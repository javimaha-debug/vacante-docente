<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proceso extends Model
{
    use HasFactory;

    protected $table = 'procesos';

    protected $fillable = [
        'ccaa_id',
        'colectivo_id',
        'anyo',
        'curso',
        'nombre',
        'estado',
        'fecha_publicacion_vacantes',
        'fecha_inicio_peticiones',
        'fecha_fin_peticiones',
        'fecha_adjudicacion',
        'pdf_vacantes_url',
        'pdf_participantes_url',
        'pdf_adjudicacion_url',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'anyo' => 'integer',
            'fecha_publicacion_vacantes' => 'date',
            'fecha_inicio_peticiones' => 'date',
            'fecha_fin_peticiones' => 'date',
            'fecha_adjudicacion' => 'date',
        ];
    }

    public function ccaa(): BelongsTo
    {
        return $this->belongsTo(Ccaa::class);
    }

    public function colectivo(): BelongsTo
    {
        return $this->belongsTo(Colectivo::class);
    }

    public function vacancies(): HasMany
    {
        return $this->hasMany(Vacancy::class);
    }
}
