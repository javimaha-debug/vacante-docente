<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncState extends Model
{
    protected $table = 'sync_states';

    protected $fillable = [
        'clave',
        'last_run_at',
        'resumen',
    ];

    protected function casts(): array
    {
        return [
            'last_run_at' => 'datetime',
            'resumen' => 'array',
        ];
    }

    /**
     * Record a completed run for the given key with a summary payload.
     *
     * @param  array<string, mixed>  $resumen
     */
    public static function record(string $clave, array $resumen): self
    {
        return static::updateOrCreate(
            ['clave' => $clave],
            ['last_run_at' => now(), 'resumen' => $resumen],
        );
    }
}
