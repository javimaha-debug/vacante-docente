<?php

namespace App\Console\Commands;

use App\Models\Ccaa;
use App\Models\Centro;
use App\Services\GoogleMapsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ImportCentros extends Command
{
    protected $signature = 'centros:import
                            {--url=https://www.ceice.gva.es/va/web/centros-docentes/directorio : GVA directory URL}
                            {--file=pdfs/gva/centros.csv : Storage CSV fallback path}
                            {--geocode-limit=200 : Max centros to geocode this run}';

    protected $description = 'Import GVA school directory into centros, geocoding new entries.';

    /** Header aliases → canonical field. */
    private const ALIASES = [
        'codigo' => 'codigo', 'code' => 'codigo', 'codi' => 'codigo', 'codcentro' => 'codigo',
        'nombre' => 'nombre', 'denominacion' => 'nombre', 'denominacio' => 'nombre', 'nom' => 'nombre', 'centro' => 'nombre', 'nomcentre' => 'nombre',
        'tipo' => 'tipo', 'tipus' => 'tipo', 'naturaleza' => 'tipo',
        'localidad' => 'localidad', 'municipio' => 'localidad', 'localitat' => 'localidad', 'poblacion' => 'localidad',
        'provincia' => 'provincia',
        'direccion' => 'direccion', 'domicilio' => 'direccion', 'adreca' => 'direccion',
        'telefono' => 'telefono', 'telefon' => 'telefono', 'tel' => 'telefono',
        'email' => 'email', 'correo' => 'email', 'mail' => 'email',
        'web' => 'web', 'url' => 'web',
    ];

    public function handle(GoogleMapsService $maps): int
    {
        $rows = $this->loadRows();

        if (empty($rows)) {
            $this->error('No se obtuvieron datos de centros (ni URL ni CSV de respaldo).');

            return self::FAILURE;
        }

        $cv = Ccaa::where('code', 'CV')->first();
        if (! $cv) {
            $this->error('CCAA "CV" no encontrada. Ejecuta CcaaSeeder.');

            return self::FAILURE;
        }

        $created = 0;
        $updated = 0;
        foreach ($rows as $row) {
            if (empty($row['codigo']) || empty($row['nombre'])) {
                continue;
            }

            $centro = Centro::updateOrCreate(
                ['codigo' => (string) $row['codigo']],
                [
                    'ccaa_id' => $cv->id,
                    'nombre' => $row['nombre'],
                    'tipo' => $row['tipo'] ?? $this->inferTipo($row['nombre']),
                    'localidad' => $row['localidad'] ?? '',
                    'provincia' => $row['provincia'] ?? $this->inferProvincia((string) $row['codigo']),
                    'direccion' => $row['direccion'] ?? null,
                    'telefono' => $row['telefono'] ?? null,
                    'email' => $row['email'] ?? null,
                    'web' => $row['web'] ?? null,
                    'fuente' => 'GVA',
                ],
            );

            $centro->wasRecentlyCreated ? $created++ : $updated++;
        }

        $geocoded = $this->geocodeMissing($maps, (int) $this->option('geocode-limit'));

        $this->info("Centros: {$created} creados, {$updated} actualizados, {$geocoded} geocodificados.");

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadRows(): array
    {
        $csv = $this->fetchCsv();

        if ($csv === null) {
            return [];
        }

        return $this->parseCsv($csv);
    }

    private function fetchCsv(): ?string
    {
        $url = (string) $this->option('url');

        try {
            $response = Http::timeout(30)->get($url);
            if ($response->successful()) {
                $body = $response->body();
                // Only treat it as CSV if it looks tabular (has delimiters).
                if (str_contains($body, ';') || str_contains($body, ',')) {
                    $this->line('Datos obtenidos desde la URL de la GVA.');

                    return $body;
                }
            }
        } catch (\Throwable $e) {
            $this->warn('Fallo al descargar desde la URL: '.$e->getMessage());
        }

        $file = (string) $this->option('file');
        if (Storage::exists($file)) {
            $this->line("Usando CSV de respaldo: storage/app/{$file}");

            return Storage::get($file);
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parseCsv(string $csv): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csv));
        if (count($lines) < 2) {
            return [];
        }

        $delimiter = substr_count($lines[0], ';') >= substr_count($lines[0], ',') ? ';' : ',';
        $header = array_map(fn ($h) => $this->canonical($h), str_getcsv($lines[0], $delimiter));

        $rows = [];
        foreach (array_slice($lines, 1) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = str_getcsv($line, $delimiter);
            $row = [];
            foreach ($header as $i => $field) {
                if ($field !== null && isset($cells[$i])) {
                    $row[$field] = trim($cells[$i]);
                }
            }
            if (! empty($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function canonical(string $header): ?string
    {
        $key = preg_replace('/[^a-z]/', '', mb_strtolower(trim($header)));

        return self::ALIASES[$key] ?? null;
    }

    private function geocodeMissing(GoogleMapsService $maps, int $limit): int
    {
        if (! $maps->isConfigured() || $limit <= 0) {
            return 0;
        }

        // Only geocode rows without coordinates (cache-friendly, quota-safe).
        $centros = Centro::whereNull('latitude')->orWhereNull('longitude');
        $count = 0;

        foreach ($centros->limit($limit)->get() as $centro) {
            $address = trim(implode(', ', array_filter([
                $centro->direccion, $centro->localidad, $centro->provincia, 'España',
            ])));

            if ($address === '') {
                continue;
            }

            try {
                $result = $maps->geocode($address);
            } catch (\Throwable $e) {
                $result = null;
            }

            if ($result) {
                $centro->update(['latitude' => $result['lat'], 'longitude' => $result['lng']]);
                $count++;
            }
        }

        return $count;
    }

    private function inferProvincia(string $codigo): string
    {
        return match (substr($codigo, 0, 2)) {
            '03' => 'Alacant',
            '12' => 'Castelló',
            default => 'València',
        };
    }

    private function inferTipo(string $nombre): string
    {
        $upper = mb_strtoupper($nombre);
        foreach (['CEIP', 'IES', 'CEE', 'CIPFP', 'CRA', 'EI', 'CEP', 'CIFP', 'EOI', 'FPA'] as $t) {
            if (str_starts_with($upper, $t.' ') || $upper === $t) {
                return $t;
            }
        }

        return 'Otro';
    }
}
