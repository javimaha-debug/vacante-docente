<?php

namespace App\Console\Commands;

use App\Models\Centro;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Enriches centros with official contact data from the GVA open-data dataset
 * "Centros docentes de la Comunitat Valenciana" (dadesobertes.gva.es).
 *
 * A single CSV (bundled at database/data/centros-gva.csv, refreshable with
 * --download) is matched against our centros by `codigo`. This replaced the
 * old per-centro REST scraping, which the GVA retired.
 */
class EnrichCentrosGva extends Command
{
    protected $signature = 'centros:enrich-gva
                            {--file= : Ruta al CSV (por defecto database/data/centros-gva.csv)}
                            {--download : Descargar el CSV actualizado del portal de datos abiertos antes de procesar}
                            {--codigo= : Enriquecer un solo centro por su código}
                            {--only-missing : Solo centros sin web/teléfono/dirección oficial}';

    protected $description = 'Enriquece los centros (web, teléfono, dirección, coordenadas) desde el dataset abierto de la GVA.';

    private const OPENDATA_URL = 'https://dadesobertes.gva.es/dataset/68eb1d94-76d3-4305-8507-e1aab7717d0e/resource/1aa53c3a-4639-41aa-ac85-d58254c428c0/download/centros-docentes-de-la-comunitat-valenciana.csv';

    public function handle(): int
    {
        $path = $this->option('file') ?: database_path('data/centros-gva.csv');

        if ($this->option('download')) {
            $this->info('Descargando CSV de datos abiertos de la GVA…');
            try {
                $res = Http::timeout(60)->get(self::OPENDATA_URL);
                if (! $res->successful()) {
                    $this->error("No se pudo descargar el CSV (HTTP {$res->status()}).");

                    return self::FAILURE;
                }
                @mkdir(dirname($path), 0775, true);
                file_put_contents($path, $res->body());
                $this->info('CSV actualizado en '.$path);
            } catch (\Throwable $e) {
                $this->error('Fallo al descargar el CSV: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        if (! is_file($path)) {
            $this->error("No se encuentra el CSV en {$path}. Usa --download o --file=…");

            return self::FAILURE;
        }

        $map = $this->parseCsv($path);
        if (empty($map)) {
            $this->error('El CSV no contiene filas legibles.');

            return self::FAILURE;
        }
        $this->info(count($map).' centros en el CSV de la GVA.');

        $query = Centro::query();
        if ($codigo = $this->option('codigo')) {
            $query->where('codigo', $codigo);
        }
        if ($this->option('only-missing')) {
            $query->where(fn ($q) => $q->whereNull('web')
                ->orWhereNull('telefono')
                ->orWhereNull('direccion_oficial'));
        }

        // Snapshot the target ids up front, then process in batches by id. This
        // avoids chunkById pitfalls (cursor desync, or rows shifting out of the
        // --only-missing filter as we write the very columns it filters on).
        $ids = $query->pluck('id');
        $total = $ids->count();
        if ($total === 0) {
            $this->info('No hay centros que enriquecer con esos filtros.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $updated = 0;
        $unchanged = 0;
        $noMatch = 0;

        foreach ($ids->chunk(200) as $batch) {
            foreach (Centro::whereIn('id', $batch)->get() as $centro) {
                $row = $map[$centro->codigo] ?? $map[ltrim($centro->codigo, '0')] ?? null;
                if (! $row) {
                    $noMatch++;
                } else {
                    $attrs = $this->attributesFromRow($row, $centro);
                    if ($attrs) {
                        $centro->fill($attrs)->save();
                        $updated++;
                    } else {
                        $unchanged++;
                    }
                }
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Actualizados: {$updated}");
        $this->line("Sin cambios (sin datos nuevos): {$unchanged}");
        $this->line("Sin coincidencia en el CSV: {$noMatch}");

        return self::SUCCESS;
    }

    /**
     * Parse the GVA CSV (";"-delimited) into [codigo => assoc row].
     *
     * @return array<string, array<string, string>>
     */
    private function parseCsv(string $path): array
    {
        $fh = fopen($path, 'r');
        if (! $fh) {
            return [];
        }

        $header = fgetcsv($fh, 0, ';');
        if (! $header) {
            fclose($fh);

            return [];
        }
        // Strip a possible UTF-8 BOM from the first header cell.
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        $idx = array_flip($header);

        if (! isset($idx['codigo'])) {
            fclose($fh);

            return [];
        }

        $map = [];
        while (($row = fgetcsv($fh, 0, ';')) !== false) {
            $codigo = trim($row[$idx['codigo']] ?? '');
            if ($codigo === '') {
                continue;
            }
            $assoc = [];
            foreach ($idx as $key => $i) {
                $assoc[$key] = isset($row[$i]) ? trim((string) $row[$i]) : '';
            }
            $map[$codigo] = $assoc;
        }
        fclose($fh);

        return $map;
    }

    /**
     * Build the attributes to update from a CSV row, only setting what we can
     * improve (don't overwrite verified coordinates).
     *
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private function attributesFromRow(array $row, Centro $centro): array
    {
        $attrs = [];

        $direccion = $this->composeAddress($row);
        if ($direccion !== '') {
            $attrs['direccion_oficial'] = mb_substr($direccion, 0, 255);
        }

        $telefono = $row['telefono'] ?? '';
        if ($telefono !== '') {
            $attrs['telefono'] = mb_substr($telefono, 0, 20);
        }

        $web = $row['url_es'] ?? ($row['url_va'] ?? '');
        if ($web !== '') {
            $attrs['web'] = mb_substr($web, 0, 200);
        }

        // Coordinates: only fill when missing, so we never clobber verified ones.
        $lng = $row['longitud'] ?? '';
        $lat = $row['latitud'] ?? '';
        if ($centro->latitude === null && is_numeric($lat) && is_numeric($lng) && (float) $lat !== 0.0) {
            $attrs['latitude'] = (float) $lat;
            $attrs['longitude'] = (float) $lng;
        }

        return $attrs;
    }

    /**
     * Compose a readable postal address from the CSV columns.
     *
     * @param  array<string, string>  $row
     */
    private function composeAddress(array $row): string
    {
        $via = trim(($row['tipo_via'] ?? '').' '.($row['direccion'] ?? ''));
        $numero = $row['numero'] ?? '';
        if ($numero !== '' && mb_strtoupper($numero) !== 'S/N') {
            $via = trim($via.', '.$numero);
        } elseif (mb_strtoupper($numero) === 'S/N') {
            $via = trim($via.', s/n');
        }

        $cpLoc = trim(($row['codigo_postal'] ?? '').' '.($row['localidad'] ?? ''));

        return trim($via.($cpLoc !== '' ? ', '.$cpLoc : ''), ', ');
    }
}
