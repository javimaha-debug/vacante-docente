<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConvocatoriaAlert extends Model
{
    protected $table = 'convocatoria_alerts';

    protected $fillable = [
        'user_id',
        'convocatoria_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function convocatoria(): BelongsTo
    {
        return $this->belongsTo(Convocatoria::class);
    }
}
