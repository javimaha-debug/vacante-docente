<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    /**
     * The VAPID public key the browser needs to subscribe, plus whether push
     * is configured on this server at all.
     */
    public function vapidKey(): JsonResponse
    {
        $key = config('webpush.vapid.public_key');

        return response()->json([
            'enabled' => (bool) $key,
            'public_key' => $key,
        ]);
    }

    /**
     * Store (or refresh) the caller's browser push subscription.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:1000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);

        $request->user()->pushSubscriptions()->updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?? 'aesgcm',
            ],
        );

        return response()->json(['subscribed' => true]);
    }

    /**
     * Remove a subscription (on unsubscribe / logout from a device).
     */
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:1000'],
        ]);

        $request->user()->pushSubscriptions()->where('endpoint', $data['endpoint'])->delete();

        return response()->json(['subscribed' => false]);
    }
}
