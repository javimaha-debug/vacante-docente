<?php

namespace App\Models;

use App\Support\NameMatch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParticipanteProceso extends Model
{
    use HasFactory;

    protected $table = 'participantes_proceso';

    protected $fillable = [
        'proceso_id',
        'posicion',
        'nombre_gva',
        'nombre_normalizado',
        'estado',
        'lloc_adjudicado',
        'centro_nombre',
        'localitat',
        'especialidad_codigo',
        'jornada',
        'cambio',
        'cambio_en',
    ];

    protected static function booted(): void
    {
        // Keep the accent/case-folded search column in sync on every save.
        // (Bulk insert() bypasses this, so the importer sets it explicitly.)
        static::saving(function (self $row) {
            $row->nombre_normalizado = NameMatch::fold($row->nombre_gva);
        });
    }

    protected function casts(): array
    {
        return [
            'posicion' => 'integer',
            'cambio_en' => 'datetime',
        ];
    }

    public function proceso(): BelongsTo
    {
        return $this->belongsTo(Proceso::class);
    }
}
