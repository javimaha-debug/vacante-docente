<?php

use App\Http\Middleware\EnsurePlan;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Public API routes are stateless (client-generated session_token);
        // authenticated routes use Sanctum bearer tokens (no session/CSRF).
        // The OAuth logout endpoint is a web route called with a bearer token,
        // and the Stripe webhook is a server-to-server POST verified by its
        // signature header, so both are exempt from CSRF verification.
        $middleware->validateCsrfTokens(except: [
            'auth/logout',
            'stripe/webhook',
        ]);

        // Route middleware aliases for SaaS authorization.
        $middleware->alias([
            'superadmin' => EnsureSuperAdmin::class,
            'plan' => EnsurePlan::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Report unhandled exceptions to Sentry (no-ops when SENTRY_LARAVEL_DSN
        // is unset, e.g. local/test). send_default_pii is off by default.
        Integration::handles($exceptions);

        // Always render API errors as JSON for the SPA / API consumers.
        $exceptions->shouldRenderJsonWhen(function ($request) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // Never leak database internals (SQL, table/column names) to clients.
        // In production return a generic 500; debug builds keep the detail.
        $exceptions->render(function (QueryException $e, $request) {
            if (! config('app.debug') && ($request->is('api/*') || $request->expectsJson())) {
                return response()->json(['message' => 'Se ha producido un error en el servidor.'], 500);
            }

            return null; // fall through to default rendering
        });
    })->create();
