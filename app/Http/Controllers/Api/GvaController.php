<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GvaNoticia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GvaController extends Controller
{
    /**
     * Latest official GVA notices (public).
     */
    public function index(): JsonResponse
    {
        $noticias = GvaNoticia::query()
            ->orderByDesc('fecha_publicacion')
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'titulo', 'url', 'fecha_publicacion', 'tipo', 'resumen']);

        return response()->json(['data' => $noticias]);
    }

    /**
     * Unnotified GVA items for an admin to review and trigger imports.
     * Restricted to user id=1 or users flagged is_admin.
     */
    public function adminUnnotified(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! ($user->id === 1 || $user->is_admin)) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $noticias = GvaNoticia::query()
            ->where('notificado', false)
            ->orderByDesc('id')
            ->get(['id', 'titulo', 'url', 'fecha_publicacion', 'tipo', 'resumen', 'keywords_matched', 'notificado']);

        return response()->json(['data' => $noticias]);
    }
}
