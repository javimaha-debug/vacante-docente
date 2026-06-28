<?php

namespace App\Services;

use App\Models\Vacancy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin server-side wrapper around the Google Maps Geocoding API and the
 * Distance Matrix API. The API key lives only on the server (config/services).
 */
class GoogleMapsService
{
    private const GEOCODE_URL = 'https://maps.googleapis.com/maps/api/geocode/json';

    private const DISTANCE_MATRIX_URL = 'https://maps.googleapis.com/maps/api/distancematrix/json';

    public function __construct(
        private readonly ?string $apiKey = null,
    ) {}

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Geocode a free-form address into coordinates.
     *
     * @return array{lat: float, lng: float, formatted_address: string}|null
     */
    public function geocode(string $address): ?array
    {
        $this->ensureConfigured();

        $response = Http::timeout(15)->get(self::GEOCODE_URL, [
            'address' => $address,
            'region' => 'es',
            'language' => 'es',
            'key' => $this->apiKey,
        ]);

        $data = $response->json();
        $status = $data['status'] ?? 'UNKNOWN_ERROR';

        if ($status === 'ZERO_RESULTS') {
            return null;
        }

        if (! $response->successful() || $status !== 'OK' || empty($data['results'])) {
            Log::warning('Geocoding failed', ['status' => $status, 'address' => $address]);
            throw new RuntimeException('Geocoding failed: '.$status);
        }

        $result = $data['results'][0];
        $location = $result['geometry']['location'];

        return [
            'lat' => (float) $location['lat'],
            'lng' => (float) $location['lng'],
            'formatted_address' => $result['formatted_address'] ?? $address,
        ];
    }

    /**
     * Return up to $limit address suggestions for autocomplete-style input.
     *
     * @return array<int, array{formatted_address: string, lat: float, lng: float}>
     */
    public function suggestAddresses(string $address, int $limit = 5): array
    {
        $this->ensureConfigured();

        $response = Http::timeout(15)->get(self::GEOCODE_URL, [
            'address' => $address,
            'region' => 'es',
            'language' => 'es',
            'key' => $this->apiKey,
        ]);

        $data = $response->json();
        $status = $data['status'] ?? 'UNKNOWN_ERROR';

        if ($status === 'ZERO_RESULTS') {
            return [];
        }

        if (! $response->successful() || $status !== 'OK' || empty($data['results'])) {
            Log::warning('Geocoding suggest failed', ['status' => $status, 'address' => $address]);

            return [];
        }

        return collect($data['results'])
            ->take($limit)
            ->map(fn (array $result) => [
                'formatted_address' => $result['formatted_address'] ?? $address,
                'lat' => (float) ($result['geometry']['location']['lat'] ?? 0),
                'lng' => (float) ($result['geometry']['location']['lng'] ?? 0),
            ])
            ->all();
    }

    /**
     * Compute travel time/distance from a home coordinate to a vacancy's centre.
     *
     * @return array{duration_minutes: int|null, distance_km: float|null, traffic_note: string|null}|null
     */
    public function distanceToVacancy(float $homeLat, float $homeLng, Vacancy $vacancy, string $mode): ?array
    {
        $this->ensureConfigured();

        $params = [
            'origins' => sprintf('%F,%F', $homeLat, $homeLng),
            'destinations' => $this->destinationFor($vacancy),
            'mode' => $mode,
            'language' => 'es',
            'region' => 'es',
            'units' => 'metric',
            'key' => $this->apiKey,
        ];

        // Traffic-aware driving: depart next Monday at 08:00 local time.
        if ($mode === 'driving') {
            $params['departure_time'] = $this->nextMondayMorning()->timestamp;
            $params['traffic_model'] = 'best_guess';
        }

        if ($mode === 'transit') {
            $params['departure_time'] = $this->nextMondayMorning()->timestamp;
        }

        $response = Http::timeout(20)->get(self::DISTANCE_MATRIX_URL, $params);
        $data = $response->json();
        $status = $data['status'] ?? 'UNKNOWN_ERROR';

        if (! $response->successful() || $status !== 'OK') {
            Log::warning('Distance Matrix request failed', ['status' => $status, 'vacancy' => $vacancy->id]);
            throw new RuntimeException('Distance Matrix failed: '.$status);
        }

        $element = $data['rows'][0]['elements'][0] ?? null;

        if (! $element || ($element['status'] ?? '') !== 'OK') {
            // No route found (e.g. transit not available) — record as a miss, not an error.
            return [
                'duration_minutes' => null,
                'distance_km' => null,
                'traffic_note' => $element['status'] ?? 'NO_RESULT',
            ];
        }

        // Prefer traffic-aware duration when available.
        $durationSeconds = $element['duration_in_traffic']['value']
            ?? $element['duration']['value']
            ?? null;

        $trafficNote = null;
        if ($mode === 'driving' && isset($element['duration_in_traffic'], $element['duration'])) {
            $delta = (int) round(($element['duration_in_traffic']['value'] - $element['duration']['value']) / 60);
            $trafficNote = $delta > 0
                ? "+{$delta} min por tráfico (lun. 08:00)"
                : 'Sin retraso por tráfico (lun. 08:00)';
        }

        return [
            'duration_minutes' => $durationSeconds !== null ? (int) round($durationSeconds / 60) : null,
            'distance_km' => isset($element['distance']['value'])
                ? round($element['distance']['value'] / 1000, 2)
                : null,
            'traffic_note' => $trafficNote,
        ];
    }

    /**
     * Batch variant: travel time/distance from one home coordinate to many
     * vacancies in a single Distance Matrix request (max 25 destinations per
     * call). Returns a map keyed by vacancy id.
     *
     * @param  \Illuminate\Support\Collection<int, Vacancy>|array<int, Vacancy>  $vacancies
     * @return array<int, array{duration_minutes: int|null, distance_km: float|null, traffic_note: string|null}>
     */
    public function distancesToVacancies(float $homeLat, float $homeLng, $vacancies, string $mode): array
    {
        $this->ensureConfigured();

        $vacancies = collect($vacancies)->values();
        $out = [];

        // Distance Matrix allows up to 25 destinations per request.
        foreach ($vacancies->chunk(25) as $chunk) {
            $chunk = $chunk->values();
            $destinations = $chunk->map(fn (Vacancy $v) => $this->destinationFor($v))->implode('|');

            $params = [
                'origins' => sprintf('%F,%F', $homeLat, $homeLng),
                'destinations' => $destinations,
                'mode' => $mode,
                'language' => 'es',
                'region' => 'es',
                'units' => 'metric',
                'key' => $this->apiKey,
            ];

            if ($mode === 'driving' || $mode === 'transit') {
                $params['departure_time'] = $this->nextMondayMorning()->timestamp;
                if ($mode === 'driving') {
                    $params['traffic_model'] = 'best_guess';
                }
            }

            $response = Http::timeout(30)->get(self::DISTANCE_MATRIX_URL, $params);
            $data = $response->json();
            $status = $data['status'] ?? 'UNKNOWN_ERROR';

            if (! $response->successful() || $status !== 'OK') {
                Log::warning('Distance Matrix batch failed', ['status' => $status]);
                throw new RuntimeException('Distance Matrix failed: '.$status);
            }

            $elements = $data['rows'][0]['elements'] ?? [];

            foreach ($chunk as $i => $vacancy) {
                $out[$vacancy->id] = $this->parseElement($elements[$i] ?? null, $mode);
            }
        }

        return $out;
    }

    /**
     * Direction-aware batch matrix: outbound ("ida", home → centre) or return
     * ("tornada", centre → home) for a given mode and departure time.
     * Up to 25 vacancies per Distance Matrix request.
     *
     * @param  \Illuminate\Support\Collection<int, Vacancy>|array<int, Vacancy>  $vacancies
     * @return array<int, array{duration_minutes: int|null, distance_km: float|null, traffic_note: string|null}>
     */
    public function travelMatrix(float $homeLat, float $homeLng, $vacancies, string $mode, string $direction, ?int $departureTs): array
    {
        $this->ensureConfigured();

        $vacancies = collect($vacancies)->values();
        $home = sprintf('%F,%F', $homeLat, $homeLng);
        $isReturn = $direction === 'tornada';
        $out = [];

        foreach ($vacancies->chunk(25) as $chunk) {
            $chunk = $chunk->values();
            $centres = $chunk->map(fn (Vacancy $v) => $this->destinationFor($v))->implode('|');

            $params = [
                'origins' => $isReturn ? $centres : $home,
                'destinations' => $isReturn ? $home : $centres,
                'mode' => $mode,
                'language' => 'es',
                'region' => 'es',
                'units' => 'metric',
                'key' => $this->apiKey,
            ];

            if ($departureTs && ($mode === 'driving' || $mode === 'transit')) {
                $params['departure_time'] = $departureTs;
                if ($mode === 'driving') {
                    $params['traffic_model'] = 'best_guess';
                }
            }

            $response = Http::timeout(30)->get(self::DISTANCE_MATRIX_URL, $params);
            $data = $response->json();
            $status = $data['status'] ?? 'UNKNOWN_ERROR';

            if (! $response->successful() || $status !== 'OK') {
                Log::warning('Distance Matrix matrix failed', ['status' => $status, 'mode' => $mode, 'dir' => $direction]);
                throw new RuntimeException('Distance Matrix failed: '.$status);
            }

            foreach ($chunk as $i => $vacancy) {
                // ida: one origin (home), N destinations → rows[0].elements[i].
                // tornada: N origins (centres), one destination → rows[i].elements[0].
                $element = $isReturn
                    ? ($data['rows'][$i]['elements'][0] ?? null)
                    : ($data['rows'][0]['elements'][$i] ?? null);
                $out[$vacancy->id] = $this->parseElement($element, $mode);
            }
        }

        return $out;
    }

    /**
     * Normalise a single Distance Matrix element into our payload shape.
     *
     * @param  array<string, mixed>|null  $element
     * @return array{duration_minutes: int|null, distance_km: float|null, traffic_note: string|null}
     */
    private function parseElement(?array $element, string $mode): array
    {
        if (! $element || ($element['status'] ?? '') !== 'OK') {
            return [
                'duration_minutes' => null,
                'distance_km' => null,
                'traffic_note' => $element['status'] ?? 'NO_RESULT',
            ];
        }

        $durationSeconds = $element['duration_in_traffic']['value']
            ?? $element['duration']['value']
            ?? null;

        $trafficNote = null;
        if ($mode === 'driving' && isset($element['duration_in_traffic'], $element['duration'])) {
            $delta = (int) round(($element['duration_in_traffic']['value'] - $element['duration']['value']) / 60);
            $trafficNote = $delta > 0
                ? "+{$delta} min por tráfico (lun. 08:00)"
                : 'Sin retraso por tráfico (lun. 08:00)';
        }

        return [
            'duration_minutes' => $durationSeconds !== null ? (int) round($durationSeconds / 60) : null,
            'distance_km' => isset($element['distance']['value'])
                ? round($element['distance']['value'] / 1000, 2)
                : null,
            'traffic_note' => $trafficNote,
        ];
    }

    /**
     * Build a geocodable destination string from the vacancy's centre data.
     */
    public function destinationFor(Vacancy $vacancy): string
    {
        return collect([
            $vacancy->centro_nombre,
            $vacancy->localidad,
            $vacancy->provincia,
            'España',
        ])->filter()->implode(', ');
    }

    /**
     * Next Monday at 08:00 in the application timezone (always in the future).
     */
    public function nextMondayMorning(): Carbon
    {
        return Carbon::now()
            ->next(Carbon::MONDAY)
            ->setTime(8, 0, 0);
    }

    /**
     * Next Monday at HH:MM (defaults to 08:00) — a representative school day in
     * the future, used as the traffic-aware departure time.
     */
    public function nextMondayAt(?string $time): Carbon
    {
        [$h, $m] = array_pad(explode(':', (string) $time), 2, '0');

        return Carbon::now()
            ->next(Carbon::MONDAY)
            ->setTime((int) $h ?: 8, (int) $m, 0);
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('GOOGLE_MAPS_API_KEY is not configured.');
        }
    }
}
