<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CalculateDistancesRequest;
use App\Models\UserList;
use App\Models\Vacancy;
use App\Services\DistanceCacheRepository;
use App\Services\GoogleMapsService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DistanceController extends Controller
{
    /** Max new Google lookups per request; the client loops until done. */
    private const MAX_LOOKUPS_PER_REQUEST = 250;

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
        $force = $request->boolean('force');
        $depTs = $this->maps->nextMondayAt($request->input('dep_time', '07:30'))->timestamp;
        $retTs = $this->maps->nextMondayAt($request->input('ret_time', '14:30'))->timestamp;

        // Target set: the explicit list sent by the client (the full loaded /
        // filtered explorer) or — for backward compatibility — the selected
        // vacancies. Distances are computed for every vacancy so the user can
        // organize by distance without selecting first.
        $requestedIds = $request->input('vacancy_ids');
        $targetIds = is_array($requestedIds) && count($requestedIds)
            ? $requestedIds
            : $userList->preferences()->where('status', 'selected')->pluck('vacancy_id')->all();

        $vacancies = Vacancy::query()->whereIn('id', $targetIds)->get();

        $entries = [];
        foreach ($vacancies as $vacancy) {
            $entries[$vacancy->id] = ['vacancy_id' => $vacancy->id];
        }

        // Build the work plan: outbound + return for driving/transit (traffic
        // at the chosen times); walking is symmetric so outbound only.
        $plan = [];
        foreach ($modes as $mode) {
            if ($mode === 'walking') {
                $plan[] = ['mode' => 'walking', 'dir' => 'ida', 'ts' => null];
            } else {
                $plan[] = ['mode' => $mode, 'dir' => 'ida', 'ts' => $depTs];
                $plan[] = ['mode' => $mode, 'dir' => 'tornada', 'ts' => $retTs];
            }
        }

        $apiError = null;
        // Cap new Google lookups per request; the client re-calls until done.
        $budget = self::MAX_LOOKUPS_PER_REQUEST;
        $remaining = 0;

        foreach ($plan as $step) {
            $key = $step['mode'].'_'.$step['dir']; // e.g. driving_ida, transit_tornada
            $uncached = [];

            foreach ($vacancies as $vacancy) {
                $cached = $force ? null : $this->cache->find($vacancy->id, $homeLat, $homeLng, $key);
                if ($cached) {
                    $entries[$vacancy->id][$key] = $this->cache->payload($cached);
                } else {
                    $uncached[] = $vacancy;
                }
            }

            if (empty($uncached)) {
                continue;
            }

            $toProcess = $budget > 0 ? array_slice($uncached, 0, $budget) : [];
            $budget -= count($toProcess);
            $remaining += count($uncached) - count($toProcess);

            if (empty($toProcess)) {
                continue;
            }

            try {
                $batch = $this->maps->travelMatrix($homeLat, $homeLng, $toProcess, $step['mode'], $step['dir'], $step['ts']);
                foreach ($toProcess as $vacancy) {
                    $payload = $batch[$vacancy->id] ?? [
                        'duration_minutes' => null, 'distance_km' => null, 'traffic_note' => 'NO_RESULT',
                    ];
                    $stored = $this->cache->store($vacancy->id, $homeLat, $homeLng, $key, $payload);
                    $entries[$vacancy->id][$key] = $this->cache->payload($stored);
                }
            } catch (QueryException $e) {
                // DB-level failure (e.g. a leftover CHECK constraint) — log the
                // detail but show the user something readable, not raw SQL.
                Log::error('Distance cache write failed', ['error' => $e->getMessage(), 'mode' => $key]);
                $apiError = 'No se pudieron guardar los tiempos calculados. Inténtalo de nuevo en unos minutos.';
                foreach ($toProcess as $vacancy) {
                    $entries[$vacancy->id][$key] = null;
                }
            } catch (RuntimeException $e) {
                $apiError = $e->getMessage();
                foreach ($toProcess as $vacancy) {
                    $entries[$vacancy->id][$key] = null;
                }
            }
        }

        return response()->json([
            'results' => array_values($entries),
            'count' => count($entries),
            'remaining' => $remaining,
            'modes' => $modes,
            'error' => $apiError,
        ]);
    }
}
