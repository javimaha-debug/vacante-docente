<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Whether real pgvector similarity search is available. True only on PostgreSQL
 * with the `vector` extension installed; otherwise callers fall back to the
 * in-PHP cosine path (which also works on MySQL/SQLite and on a Postgres box
 * that doesn't have pgvector). Cached per request.
 */
class Vector
{
    private static ?bool $enabled = null;

    public static function enabled(): bool
    {
        if (self::$enabled !== null) {
            return self::$enabled;
        }

        try {
            if (DB::getDriverName() !== 'pgsql') {
                return self::$enabled = false;
            }

            return self::$enabled = ! empty(DB::select("SELECT 1 FROM pg_extension WHERE extname = 'vector'"));
        } catch (\Throwable $e) {
            return self::$enabled = false;
        }
    }

    /** Test helper: reset the cached value. */
    public static function flush(): void
    {
        self::$enabled = null;
    }
}
