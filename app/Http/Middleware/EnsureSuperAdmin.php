<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict a route to administrators. Both 'admin' and 'superadmin' roles
 * pass; everyone else (including unauthenticated requests) gets a 403.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, ['admin', 'superadmin'], true)) {
            return response()->json([
                'message' => 'No tienes permiso para acceder a esta sección.',
            ], 403);
        }

        return $next($request);
    }
}
