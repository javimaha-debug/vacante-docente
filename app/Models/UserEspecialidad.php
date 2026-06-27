<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserEspecialidad extends Model
{
    use HasFactory;

    protected $table = 'user_especialidades';

    protected $fillable = [
        'user_id',
        'specialty_id',
        'posicion_bolsa',
        'estado_bolsa',
        'anyo',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'posicion_bolsa' => 'integer',
            'anyo' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }
}
