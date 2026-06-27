<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GvaNoticia;
use Illuminate\Http\JsonResponse;

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
}
