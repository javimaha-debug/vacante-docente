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

        $vacancies = Vacancy::query()
            ->whereIn('id', $userList->preferences()
                ->where('status', 'selected')
                ->pluck('vacancy_id'))
            ->get();

        $results = [];
        $apiError = null;

        foreach ($vacancies as $vacancy) {
            $entry = ['vacancy_id' => $vacancy->id];

            foreach ($modes as $mode) {
                $cached = $this->cache->find($vacancy->id, $homeLat, $homeLng, $mode);

                if ($cached) {
                    $entry[$mode] = $this->cache->payload($cached);

                    continue;
                }

                try {
                    $payload = $this->maps->distanceToVacancy($homeLat, $homeLng, $vacancy, $mode);
                    $stored = $this->cache->store($vacancy->id, $homeLat, $homeLng, $mode, $payload);
                    $entry[$mode] = $this->cache->payload($stored);
                } catch (RuntimeException $e) {
                    $apiError = $e->getMessage();
                    $entry[$mode] = null;
                }
            }

            $results[] = $entry;
        }

        return response()->json([
            'results' => $results,
            'count' => count($results),
            'modes' => $modes,
            'error' => $apiError,
        ]);
    }
}
