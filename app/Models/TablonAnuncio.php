<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TablonAnuncio extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tablon_anuncios';

    protected $fillable = [
        'user_id',
        'ccaa_id',
        'categoria',
        'titulo',
        'contenido',
        'localidad_origen',
        'localidad_destino',
        'centro_id',
        'specialty_id',
        'contacto_email',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ccaa(): BelongsTo
    {
        return $this->belongsTo(Ccaa::class);
    }

    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class);
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function contactos(): HasMany
    {
        return $this->hasMany(TablonContacto::class, 'anuncio_id');
    }
}
