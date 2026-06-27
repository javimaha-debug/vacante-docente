<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ccaa extends Model
{
    use HasFactory;

    protected $table = 'ccaas';

    protected $fillable = [
        'code',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function colectivos(): HasMany
    {
        return $this->hasMany(Colectivo::class);
    }

    public function procesos(): HasMany
    {
        return $this->hasMany(Proceso::class);
    }

    public function centros(): HasMany
    {
        return $this->hasMany(Centro::class);
    }
}
