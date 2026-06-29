<?php

namespace App\Support;

/**
 * Accent- and case-insensitive name matching.
 *
 * GVA participant names carry Spanish/Valencian accents (PÉREZ, GARCÍA, JOSÉ,
 * ANNA). PHP's mb_strtolower folds case for accented letters, but the SQL
 * `LOWER()` of SQLite/MySQL (without an ICU/collation setup) does NOT — so a
 * needle lowercased in PHP never matches a column lowercased in SQL whenever an
 * accent is involved. The result: searches (and even finding yourself) return
 * nothing for any name with an accent.
 *
 * This helper folds a name identically wherever it is needed: the importers
 * persist the folded form in a `nombre_normalizado` column, and searches fold
 * the needle the same way before comparing against that column — so matching is
 * a plain, indexable equality/LIKE with no DB-specific collation tricks.
 */
class NameMatch
{
    /** Accented character → unaccented lowercase equivalent (both letter cases). */
    private const MAP = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'Á' => 'a', 'À' => 'a', 'Ä' => 'a', 'Â' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e', 'É' => 'e', 'È' => 'e', 'Ë' => 'e', 'Ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i', 'Í' => 'i', 'Ì' => 'i', 'Ï' => 'i', 'Î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'Ó' => 'o', 'Ò' => 'o', 'Ö' => 'o', 'Ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u', 'Ú' => 'u', 'Ù' => 'u', 'Ü' => 'u', 'Û' => 'u',
        'ñ' => 'n', 'Ñ' => 'n', 'ç' => 'c', 'Ç' => 'c',
    ];

    /** Fold a name: strip the common accents and lowercase. */
    public static function fold(?string $value): string
    {
        return mb_strtolower(strtr((string) $value, self::MAP));
    }

    /** Escape LIKE wildcards in an (already folded) needle. */
    public static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
