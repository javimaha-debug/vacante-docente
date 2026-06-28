<?php

namespace App\Services;

use App\Models\Centro;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Enriches centros with fresh data from the GVA official directory REST API,
 * falling back to Google geocoding for coordinates.
 *
 * The exact field names of the GVA payload vary, so extraction is tolerant:
 * the JSON is flattened and values are picked by a list of candidate keys.
 */
class GvaCentrosService
{
    private const DETALLE_URL = 'https://www.ceice.gva.es/opencms/rest/centros/detalle';

    public function __construct(private readonly GoogleMapsService $maps) {}

    /**
     * Fetch + map + persist fresh data for one centro.
     *
     * @return array{status: string, geocoded: bool, raw?: array}
     */
    public function enrich(Centro $centro, bool $debug = false): array
    {
        $json = $this->fetchDetalle($centro->codigo);
        if ($json === null) {
            return ['status' => 'not_found', 'geocoded' => false];
        }

        $attrs = $this->mapAttributes($json);
        $geocoded = false;

        // Coordinates: prefer the API; otherwise geocode the postal address.
        if (! isset($attrs['latitude'], $attrs['longitude']) && $this->maps->isConfigured()) {
            $address = collect([
                $attrs['direccion'] ?? $centro->direccion,
                $attrs['localidad'] ?? $centro->localidad,
                $attrs['provincia'] ?? $centro->provincia,
                'España',
            ])->filter()->implode(', ');

            if ($address !== '') {
                try {
                    $geo = $this->maps->geocode($address);
                    if ($geo) {
                        $attrs['latitude'] = $geo['lat'];
                        $attrs['longitude'] = $geo['lng'];
                        $geocoded = true;
                    }
                } catch (\Throwable $e) {
                    Log::warning('GVA enrich geocode failed', ['codigo' => $centro->codigo, 'error' => $e->getMessage()]);
                }
            }
        }

        if (empty($attrs)) {
            return ['status' => 'empty', 'geocoded' => false, 'raw' => $debug ? $json : []];
        }

        $centro->fill($attrs);
        $centro->datos_verificados = true;
        $centro->fuente = 'GVA';
        $centro->save();

        return ['status' => 'updated', 'geocoded' => $geocoded, 'raw' => $debug ? $json : []];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchDetalle(string $codigo): ?array
    {
        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->get(self::DETALLE_URL, ['codigoCentro' => $codigo]);
        } catch (\Throwable $e) {
            Log::warning('GVA detalle request failed', ['codigo' => $codigo, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) && ! empty($data) ? $data : null;
    }

    /**
     * Map the (flattened) GVA payload to centro columns, trimmed to length.
     *
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    public function mapAttributes(array $json): array
    {
        $flat = [];
        $this->flatten($json, $flat);

        $pick = fn (array $keys) => collect($keys)
            ->map(fn ($k) => $flat[mb_strtolower($k)] ?? null)
            ->first(fn ($v) => filled($v));

        $cut = fn ($v, int $n) => $v === null ? null : mb_substr(trim((string) $v), 0, $n);

        $attrs = array_filter([
            'nombre' => $cut($pick(['denominacioCompleta', 'denominacionCompleta', 'denominacion', 'denominacio', 'nombre', 'nom']), 200),
            'direccion' => $cut($pick(['domicilio', 'direccion', 'adreca', 'via', 'domicili']), 200),
            'telefono' => $cut($pick(['telefono', 'telefon', 'tlf', 'tfno']), 20),
            'email' => $cut($pick(['email', 'correo', 'correoElectronico', 'mail', 'correu']), 100),
            'web' => $cut($pick(['web', 'url', 'paginaweb', 'pagWeb']), 200),
            'localidad' => $cut($pick(['localidad', 'localitat', 'poblacion', 'poblacio', 'municipio', 'municipi']), 100),
            'provincia' => $cut($pick(['provincia']), 50),
        ], fn ($v) => filled($v));

        $lat = $pick(['latitud', 'latitude', 'lat', 'gmapsLat']);
        $lng = $pick(['longitud', 'longitude', 'lng', 'lon', 'gmapsLng']);
        if (is_numeric($lat) && is_numeric($lng) && (float) $lat !== 0.0) {
            $attrs['latitude'] = (float) $lat;
            $attrs['longitude'] = (float) $lng;
        }

        return $attrs;
    }

    /**
     * Flatten a nested array into a [lowercased-key => first non-empty scalar] map.
     *
     * @param  array<mixed>  $data
     * @param  array<string, mixed>  $out
     */
    private function flatten($data, array &$out): void
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $this->flatten($v, $out);
            } elseif (is_scalar($v) && $v !== '' && is_string($k)) {
                $lk = mb_strtolower($k);
                if (! isset($out[$lk]) || $out[$lk] === '' || $out[$lk] === null) {
                    $out[$lk] = $v;
                }
            }
        }
    }
}
