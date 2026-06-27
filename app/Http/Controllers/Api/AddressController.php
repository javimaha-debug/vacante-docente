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

        return response()->json([
            'data' => $this->maps->suggestAddresses($data['address']),
            'configured' => true,
        ]);
    }
}
