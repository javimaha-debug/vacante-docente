<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CalculateDistancesRequest;
use App\Models\UserList;
use App\Models\Vacancy;
use App\Services\DistanceCacheRepository;
use App\Services\GoogleMapsService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class DistanceController extends Controller
{
    public function __construct(
        private readonly GoogleMapsService $maps,
        private readonly DistanceCacheRepository $cache,
    ) {}

    /**
     * Calculate travel times for every SELECTED vacancy in the list, using the
     * cache first and falling back to the Google Distance Matrix API.
     */
    public function __invoke(CalculateDistancesRequest $request, UserList $userList): JsonResponse
    {
        if (! $userList->hasHome()) {
            return response()->json([
                'message' => 'Primero debes geolocalizar una dirección de origen.',
            ], 422);
        }

        if (! $this->maps->isConfigured()) {
            return response()->json([
                'message' => 'El servicio de mapas no está configurado (falta GOOGLE_MAPS_API_KEY).',
            ], 503);
        }

        $modes = $request->modes();
        $homeLat = (float) $userList->home_lat;
        $homeLng = (float) $userList->home_lng;

        // Target set: the explicit list sent by the client (the full loaded /
        // filtered explorer) or — for backward compatibility — the selected
        // vacancies. This lets distances be computed for every vacancy so the
        // user can organize by distance without selecting first.
        $requestedIds = $request->input('vacancy_ids');
        $targetIds = is_array($requestedIds) && count($requestedIds)
            ? $requestedIds
            : $userList->preferences()->where('status', 'selected')->pluck('vacancy_id')->all();

        $vacancies = Vacancy::query()->whereIn('id', $targetIds)->get();

        $entries = [];
        foreach ($vacancies as $vacancy) {
            $entries[$vacancy->id] = ['vacancy_id' => $vacancy->id];
        }

        $apiError = null;

        // Compute each mode in batches, hitting the cache first.
        foreach ($modes as $mode) {
            $uncached = [];

            foreach ($vacancies as $vacancy) {
                $cached = $this->cache->find($vacancy->id, $homeLat, $homeLng, $mode);
                if ($cached) {
                    $entries[$vacancy->id][$mode] = $this->cache->payload($cached);
                } else {
                    $uncached[] = $vacancy;
                }
            }

            if (empty($uncached)) {
                continue;
            }

            try {
                $batch = $this->maps->distancesToVacancies($homeLat, $homeLng, $uncached, $mode);
                foreach ($uncached as $vacancy) {
                    $payload = $batch[$vacancy->id] ?? [
                        'duration_minutes' => null, 'distance_km' => null, 'traffic_note' => 'NO_RESULT',
                    ];
                    $stored = $this->cache->store($vacancy->id, $homeLat, $homeLng, $mode, $payload);
                    $entries[$vacancy->id][$mode] = $this->cache->payload($stored);
                }
            } catch (RuntimeException $e) {
                $apiError = $e->getMessage();
                foreach ($uncached as $vacancy) {
                    $entries[$vacancy->id][$mode] = null;
                }
            }
        }

        return response()->json([
            'results' => array_values($entries),
            'count' => count($entries),
            'modes' => $modes,
            'error' => $apiError,
        ]);
    }
}
