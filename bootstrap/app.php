<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
            'superadmin' => \App\Http\Middleware\EnsureSuperAdmin::class,
            'plan' => \App\Http\Middleware\EnsurePlan::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Always render API errors as JSON for the SPA / API consumers.
        $exceptions->shouldRenderJsonWhen(function ($request) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })->create();
