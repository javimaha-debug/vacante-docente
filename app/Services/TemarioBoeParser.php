<?php

namespace App\Services;

/**
 * Parses the plain text extracted from a BOE temario Order (EDU/3136/2011 for
 * Maestros, EDU/3138/2011 for Secundaria/FP) into specialties and their temas.
 *
 * The parsing is line-oriented and tolerant: a specialty header opens a block,
 * and numbered "Tema N." / "N." lines inside it become temas (titles may wrap
 * across the following lines until the next tema or header).
 */
class TemarioBoeParser
{
    /**
     * @return array<int, array{especialidad_nombre:string, temas:array<int, array{numero:int, titulo:string}>}>
     */
    public function parse(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];

        $especialidades = [];
        $current = null;          // current especialidad bucket
        $pendingTema = null;      // tema whose title may still be wrapping

        $flushTema = function () use (&$current, &$pendingTema) {
            if ($current !== null && $pendingTema !== null) {
                $pendingTema['titulo'] = $this->cleanTitle($pendingTema['titulo']);
                if ($pendingTema['titulo'] !== '') {
                    $current['temas'][] = $pendingTema;
                }
            }
            $pendingTema = null;
        };

        $flushEspecialidad = function () use (&$especialidades, &$current, $flushTema) {
            $flushTema();
            if ($current !== null && $current['temas'] !== []) {
                $especialidades[] = $current;
            }
            $current = null;
        };

        foreach ($lines as $raw) {
            $line = trim(preg_replace('/\s+/', ' ', $raw));
            if ($line === '') {
                continue;
            }

            if (($nombre = $this->matchEspecialidad($line)) !== null) {
                $flushEspecialidad();
                $current = ['especialidad_nombre' => $nombre, 'temas' => []];

                continue;
            }

            if ($current !== null && ($tema = $this->matchTema($line)) !== null) {
                $flushTema();
                $pendingTema = $tema;

                continue;
            }

            // Continuation of the current tema title (wrapped line).
            if ($pendingTema !== null && ! $this->looksLikeNoise($line)) {
                $pendingTema['titulo'] .= ' '.$line;
            }
        }

        $flushEspecialidad();

        // Re-number sequentially per especialidad if the source numbering is odd,
        // but keep the detected numbers when they form a clean 1..N run.
        return $especialidades;
    }

    /** Detect a specialty header line; returns the specialty name or null. */
    private function matchEspecialidad(string $line): ?string
    {
        if (preg_match('/^Especialidad(?:\s+de)?\s*[:\.]?\s*(.+)$/iu', $line, $m)) {
            $name = trim($m[1], " .:\t");
            if (mb_strlen($name) >= 3 && ! preg_match('/^\d/', $name)) {
                return $name;
            }
        }
        if (preg_match('/^Cuerpo\s+de\s+.+?,?\s+Especialidad\s+de\s+(.+)$/iu', $line, $m)) {
            return trim($m[1], " .:\t");
        }

        return null;
    }

    /**
     * Detect a tema line; returns ['numero','titulo'] or null.
     *
     * @return array{numero:int, titulo:string}|null
     */
    private function matchTema(string $line): ?array
    {
        if (preg_match('/^Tema\s+(\d{1,3})\s*[\.\-–:]\s*(.+)$/iu', $line, $m)
            || preg_match('/^(\d{1,3})\s*[\.\-–:]\s+(.+)$/u', $line, $m)) {
            $numero = (int) $m[1];
            $titulo = trim($m[2]);
            if ($numero >= 1 && $numero <= 200 && mb_strlen($titulo) >= 3) {
                return ['numero' => $numero, 'titulo' => $titulo];
            }
        }

        return null;
    }

    /** Lines that are page furniture rather than tema title continuations. */
    private function looksLikeNoise(string $line): bool
    {
        return (bool) preg_match('/^(BOLET[IÍ]N|BOE|Núm\.|Pág\.|cve:|http|\d+\s*$)/iu', $line);
    }

    private function cleanTitle(string $title): string
    {
        $title = trim(preg_replace('/\s+/', ' ', $title));

        return rtrim($title, ' .;');
    }
}
