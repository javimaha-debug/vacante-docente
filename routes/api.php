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
    Route::post('auth/login', [\App\Http\Controllers\AuthController::class, 'login'])->middleware('throttle:10,1');

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

    // GVA monitor (public): latest official notices.
    Route::get('gva/noticias', [GvaController::class, 'index']);

    // Procesos (public): list + per-proceso vacancies with filters.
    Route::get('procesos', [ProcesoController::class, 'index']);
    Route::get('procesos/{proceso}/vacantes', [ProcesoController::class, 'vacantes']);
    Route::get('procesos/{proceso}/cambios', [ProcesoController::class, 'cambios']);

    // Participant lists (public list + search).
    Route::get('participantes/{proceso}', [ParticipanteController::class, 'index']);
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
    Route::middleware('auth:sanctum')->group(function () {
        // In-app notifications inbox.
        Route::get('notificaciones', [NotificationController::class, 'index']);
        Route::post('notificaciones/leer/{id?}', [NotificationController::class, 'markRead']);

        // Web push subscription management.
        Route::post('push/subscribe', [PushSubscriptionController::class, 'store']);
        Route::post('push/unsubscribe', [PushSubscriptionController::class, 'destroy']);

        Route::get('user/me', [UserProfileController::class, 'me']);
        Route::get('user/profile', [UserProfileController::class, 'show']);
        Route::put('user/profile', [UserProfileController::class, 'update']);
        Route::post('user/especialidades', [UserProfileController::class, 'storeEspecialidad']);
        Route::delete('user/especialidades/{specialty}', [UserProfileController::class, 'destroyEspecialidad']);
        Route::get('user/dashboard', [UserProfileController::class, 'dashboard']);

        // Authenticated vacancy list (kanban) synced to the account.
        Route::get('user/lista', [UserProfileController::class, 'lista']);
        Route::put('user/lista/sync', [UserProfileController::class, 'syncLista']);

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
    });
});
