<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a route behind a paid plan. Usage: 'plan' (any paid plan) or
 * 'plan:interino,todo_en_uno' (one of the listed plan codes). Super-admins
 * always pass. On failure responds 403 with a plan_required marker and an
 * upgrade URL the SPA can redirect to.
 */
class EnsurePlan
{
    public function handle(Request $request, Closure $next, string ...$plans): Response
    {
        $user = $request->user();

        if ($user && $user->isSuperAdmin()) {
            return $next($request);
        }

        $allowed = $user && ($plans
            ? in_array($user->plan, $plans, true) && $user->plan_status === 'active'
            : $user->isPaid());

        if (! $allowed) {
            return response()->json([
                'message' => 'Esta funcionalidad requiere un plan de pago.',
                'error' => 'plan_required',
                'upgrade_url' => '/dashboard/planes',
                'required_plans' => array_values($plans),
            ], 403);
        }

        return $next($request);
    }
}
