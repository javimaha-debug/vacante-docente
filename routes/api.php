<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\CentroController;
use App\Http\Controllers\Api\DistanceController;
use App\Http\Controllers\Api\GeocodeController;
use App\Http\Controllers\Api\GvaController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ParticipanteController;
use App\Http\Controllers\Api\PushSubscriptionController;
use App\Http\Controllers\Api\PreferenceController;
use App\Http\Controllers\Api\ProcesoController;
use App\Http\Controllers\Api\SpecialtyController;
use App\Http\Controllers\Api\TablonController;
use App\Http\Controllers\Api\UserListController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Api\VacancyController;
use App\Http\Controllers\Api\SuperAdmin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\SuperAdmin\MetricasController as AdminMetricasController;
use App\Http\Controllers\Api\SuperAdmin\SistemaController as AdminSistemaController;
use App\Http\Controllers\Api\SuperAdmin\SuscripcionesController as AdminSuscripcionesController;
use App\Http\Controllers\Api\SuperAdmin\UsuariosController as AdminUsuariosController;
use App\Http\Controllers\Api\SuperAdmin\ImportacionesController as AdminImportacionesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (v1)
|--------------------------------------------------------------------------
|
| All routes are stateless. The SPA identifies a "user" through a
| client-generated session_token (see lib/session.js). Google Maps calls
| are proxied here so the API key is never exposed to the browser.
|
*/

Route::prefix('v1')->group(function () {
    // Email + password auth (public, rate limited). Social login lives on the
    // web routes (/auth/{provider}) so Socialite can redirect.
    Route::get('auth/providers', [\App\Http\Controllers\AuthController::class, 'providers']);
    Route::post('auth/register', [\App\Http\Controllers\AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('auth/login', [\App\Http\Controllers\AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('auth/exchange', [\App\Http\Controllers\AuthController::class, 'exchange'])->middleware('throttle:30,1');

    // Catalog
    Route::get('specialties', [SpecialtyController::class, 'index']);
    Route::get('vacancies', [VacancyController::class, 'index']);

    // User lists (one per session_token + specialty)
    Route::post('user-lists', [UserListController::class, 'store']);
    Route::patch('user-lists/{userList}', [UserListController::class, 'update']);

    // Preferences (kanban / sortable state)
    Route::get('user-lists/{userList}/preferences', [PreferenceController::class, 'index']);
    Route::put('user-lists/{userList}/preferences/bulk', [PreferenceController::class, 'bulk']);

    // Google Maps proxies (rate limited)
    Route::post('user-lists/{userList}/geocode', GeocodeController::class)
        ->middleware('throttle:geocode');

    Route::post('user-lists/{userList}/calculate-distances', DistanceController::class)
        ->middleware('throttle:distances');

    // Catalog (public): collectives available for the profile selector.
    Route::get('colectivos', function () {
        return response()->json([
            'data' => \App\Models\Colectivo::query()
                ->orderBy('body')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'body']),
        ]);
    });

    // Plans catalogue (public): drives the pricing / upgrade page.
    Route::get('planes', function () {
        return response()->json([
            'data' => \App\Models\Plan::query()
                ->where('activo', true)
                ->orderBy('sort_order')
                ->get(['codigo', 'nombre', 'descripcion', 'features', 'sort_order']),
        ]);
    });

    // GVA monitor (public): latest official notices.
    Route::get('gva/noticias', [GvaController::class, 'index']);

    // Procesos (public): list + per-proceso vacancies with filters.
    Route::get('procesos', [ProcesoController::class, 'index']);
    Route::get('procesos/{proceso}/vacantes', [ProcesoController::class, 'vacantes']);
    Route::get('procesos/{proceso}/cambios', [ProcesoController::class, 'cambios']);

    // Participant change summary (counts only, no names) stays public.
    Route::get('participantes/{proceso}/cambios', [ParticipanteController::class, 'cambios']);

    // Centros directory (public): list + detail.
    Route::get('centros', [CentroController::class, 'index']);
    Route::get('centros/{codigo}', [CentroController::class, 'show']);

    // Tablón de anuncios (public listing).
    Route::get('tablon', [TablonController::class, 'index']);

    // Address autocomplete (public, rate limited like geocode).
    Route::get('geocode', [AddressController::class, 'suggest'])->middleware('throttle:geocode');

    // Web push: VAPID public key (public so the SPA can check availability).
    Route::get('push/vapid-key', [PushSubscriptionController::class, 'vapidKey']);

    // Authenticated teacher profile + dashboard (Sanctum bearer token).
    // Every authenticated request refreshes the user's last_active_at stamp.
    Route::middleware(['auth:sanctum', \App\Http\Middleware\UpdateLastActive::class])->group(function () {
        // In-app notifications inbox.
        Route::get('notificaciones', [NotificationController::class, 'index']);
        Route::post('notificaciones/leer/{id?}', [NotificationController::class, 'markRead']);

        // Web push subscription management.
        Route::post('push/subscribe', [PushSubscriptionController::class, 'store']);
        Route::post('push/unsubscribe', [PushSubscriptionController::class, 'destroy']);

        Route::get('user/me', [UserProfileController::class, 'me']);
        Route::get('user/profile', [UserProfileController::class, 'show']);
        Route::put('user/profile', [UserProfileController::class, 'update']);
        Route::put('user/modo', [UserProfileController::class, 'updateModo']);
        Route::put('user/onboarding', [UserProfileController::class, 'onboarding']);
        // Exit an impersonation session (called with the impersonation token).
        Route::post('user/stop-impersonate', [AdminUsuariosController::class, 'stopImpersonate']);
        Route::post('user/especialidades', [UserProfileController::class, 'storeEspecialidad']);
        Route::delete('user/especialidades/{specialty}', [UserProfileController::class, 'destroyEspecialidad']);
        Route::get('user/dashboard', [UserProfileController::class, 'dashboard']);
        Route::get('user/adjudicaciones-continuas', [UserProfileController::class, 'adjudicacionesContinuas']);

        // Authenticated vacancy list (kanban) synced to the account.
        Route::get('user/lista', [UserProfileController::class, 'lista']);
        Route::put('user/lista/sync', [UserProfileController::class, 'syncLista']);

        // Participant list (contains names) — requires login.
        Route::get('participantes/{proceso}', [ParticipanteController::class, 'index']);

        // Participant self-lookup.
        Route::get('participantes/{proceso}/mi-posicion', [ParticipanteController::class, 'miPosicion']);

        // Community contributions for a centro.
        Route::post('centros/{codigo}/horarios', [CentroController::class, 'storeHorario']);
        Route::post('centros/{codigo}/valoraciones', [CentroController::class, 'storeValoracion']);

        // Tablón de anuncios (authenticated).
        Route::get('tablon/mis-anuncios', [TablonController::class, 'misAnuncios']);
        Route::post('tablon', [TablonController::class, 'store']);
        Route::delete('tablon/{anuncio}', [TablonController::class, 'destroy']);
        Route::post('tablon/{anuncio}/contactar', [TablonController::class, 'contactar']);
        Route::get('tablon/{anuncio}/contactos', [TablonController::class, 'contactos']);
        Route::post('tablon/contactos/{contacto}/responder', [TablonController::class, 'responder']);

        // GVA admin review (id=1 or is_admin).
        Route::get('admin/gva-noticias', [GvaController::class, 'adminUnnotified']);
        Route::get('admin/gva-importaciones', [GvaController::class, 'adminImportaciones']);
        Route::post('admin/gva-importaciones/{noticia}/reimportar', [GvaController::class, 'adminReimport']);
        Route::post('admin/procesos', [GvaController::class, 'adminCrearProcesos']);
        Route::post('admin/importaciones/manual', [GvaController::class, 'adminImportarManual']);
    });

    // SuperAdmin panel API: requires an admin/superadmin role, rate limited.
    Route::prefix('superadmin')
        ->middleware(['auth:sanctum', 'superadmin', 'throttle:120,1'])
        ->group(function () {
            Route::get('dashboard', [AdminDashboardController::class, 'index']);

            Route::get('usuarios', [AdminUsuariosController::class, 'index']);
            Route::get('usuarios/{usuario}', [AdminUsuariosController::class, 'show']);
            Route::put('usuarios/{usuario}', [AdminUsuariosController::class, 'update']);
            Route::put('usuarios/{usuario}/plan', [AdminUsuariosController::class, 'cambiarPlan']);
            Route::post('usuarios/{usuario}/notas', [AdminUsuariosController::class, 'addNota']);
            Route::post('usuarios/{usuario}/impersonate', [AdminUsuariosController::class, 'impersonate']);
            Route::post('usuarios/{usuario}/suspender', [AdminUsuariosController::class, 'suspender']);

            Route::get('suscripciones', [AdminSuscripcionesController::class, 'index']);

            Route::get('metricas', [AdminMetricasController::class, 'index']);
            Route::get('metricas/export', [AdminMetricasController::class, 'export']);

            Route::get('importaciones/health', [AdminImportacionesController::class, 'health']);
            Route::post('importaciones/run-monitor', [AdminImportacionesController::class, 'runMonitor']);

            Route::get('sistema/status', [AdminSistemaController::class, 'status']);
            Route::get('sistema/logs', [AdminSistemaController::class, 'logs']);
            Route::post('sistema/cache-clear', [AdminSistemaController::class, 'cacheClear']);
            Route::get('sistema/failed-jobs', [AdminSistemaController::class, 'failedJobs']);
        });
});
