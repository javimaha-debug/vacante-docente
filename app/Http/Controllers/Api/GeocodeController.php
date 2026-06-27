<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeocodeRequest;
use App\Models\UserList;
use App\Services\GoogleMapsService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class GeocodeController extends Controller
{
    public function __construct(private readonly GoogleMapsService $maps) {}

    /**
     * Geocode an address server-side and persist the coordinates on the list.
     */
    public function __invoke(GeocodeRequest $request, UserList $userList): JsonResponse
    {
        if (! $this->maps->isConfigured()) {
            return response()->json([
                'message' => 'El servicio de mapas no está configurado (falta GOOGLE_MAPS_API_KEY).',
            ], 503);
        }

        $address = $request->validated()['address'];

        try {
            $result = $this->maps->geocode($address);
        } catch (RuntimeException $e) {
            return response()->json(['message' => 'No se pudo geolocalizar la dirección.'], 502);
        }

        if ($result === null) {
            return response()->json(['message' => 'No se encontraron resultados para esa dirección.'], 422);
        }

        $userList->update([
            'home_address' => $result['formatted_address'],
            'home_lat' => $result['lat'],
            'home_lng' => $result['lng'],
        ]);

        return response()->json([
            'lat' => $result['lat'],
            'lng' => $result['lng'],
            'formatted_address' => $result['formatted_address'],
        ]);
    }
}
