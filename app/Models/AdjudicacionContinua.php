<?php

namespace App\Models;

use App\Support\NameMatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdjudicacionContinua extends Model
{
    protected $table = 'adjudicaciones_continuas';

    protected $fillable = [
        'curso',
        'fecha',
        'cuerpo',
        'nombre_gva',
        'nombre_normalizado',
        'especialidad_codigo',
        'posicion',
        'estado',
        'lloc_adjudicado',
        'centro_codigo',
        'centro_nombre',
        'localitat',
        'jornada',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'posicion' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Keep the accent/case-folded search column in sync on every save.
        // (Bulk insert() bypasses this, so the importer sets it explicitly.)
        static::saving(function (self $row) {
            $row->nombre_normalizado = NameMatch::fold($row->nombre_gva);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
