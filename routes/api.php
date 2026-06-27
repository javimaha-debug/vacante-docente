<?php

use App\Http\Controllers\Api\DistanceController;
use App\Http\Controllers\Api\GeocodeController;
use App\Http\Controllers\Api\GvaController;
use App\Http\Controllers\Api\PreferenceController;
use App\Http\Controllers\Api\SpecialtyController;
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

    // Authenticated teacher profile + dashboard (Sanctum bearer token).
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('user/me', [UserProfileController::class, 'me']);
        Route::get('user/profile', [UserProfileController::class, 'show']);
        Route::put('user/profile', [UserProfileController::class, 'update']);
        Route::post('user/especialidades', [UserProfileController::class, 'storeEspecialidad']);
        Route::delete('user/especialidades/{specialty}', [UserProfileController::class, 'destroyEspecialidad']);
        Route::get('user/dashboard', [UserProfileController::class, 'dashboard']);
    });
});
