<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GoogleMapsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function __construct(private readonly GoogleMapsService $maps) {}

    /**
     * Address autocomplete: up to 5 suggestions for the profile address field.
     */
    public function suggest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'address' => ['required', 'string', 'min:3', 'max:300'],
        ]);

        if (! $this->maps->isConfigured()) {
            return response()->json(['data' => [], 'configured' => false]);
        }

        // Prefer Places Autocomplete (works for partial input); fall back to the
        // Geocoding API when Places is not enabled on the key.
        try {
            $suggestions = $this->maps->autocompleteAddresses($data['address']);
        } catch (\RuntimeException $e) {
            $suggestions = $this->maps->suggestAddresses($data['address']);
        }

        return response()->json([
            'data' => $suggestions,
            'configured' => true,
        ]);
    }
}
