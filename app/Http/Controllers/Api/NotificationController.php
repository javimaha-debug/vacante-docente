<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * The authenticated user's in-app notifications (newest first) plus the
     * current unread count.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'data' => $n->data,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'data' => $notifications,
            'unread' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark a single notification as read (or all when no id is given).
     */
    public function markRead(Request $request, ?string $id = null): JsonResponse
    {
        $user = $request->user();

        if ($id) {
            $user->notifications()->where('id', $id)->whereNull('read_at')->update(['read_at' => now()]);
        } else {
            $user->unreadNotifications->markAsRead();
        }

        return response()->json(['unread' => $user->unreadNotifications()->count()]);
    }
}
