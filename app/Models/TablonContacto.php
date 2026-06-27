<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TablonContacto extends Model
{
    use HasFactory;

    protected $table = 'tablon_contactos';

    protected $fillable = [
        'anuncio_id',
        'user_id',
        'mensaje',
        'email_enviado',
        'leido',
    ];

    protected function casts(): array
    {
        return [
            'email_enviado' => 'boolean',
            'leido' => 'boolean',
        ];
    }

    public function anuncio(): BelongsTo
    {
        return $this->belongsTo(TablonAnuncio::class, 'anuncio_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
