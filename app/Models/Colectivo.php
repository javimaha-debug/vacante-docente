<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Colectivo extends Model
{
    use HasFactory;

    protected $table = 'colectivos';

    protected $fillable = [
        'ccaa_id',
        'code',
        'name',
        'body',
        'description',
    ];

    public function ccaa(): BelongsTo
    {
        return $this->belongsTo(Ccaa::class);
    }

    public function procesos(): HasMany
    {
        return $this->hasMany(Proceso::class);
    }
}
