<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| The React SPA owns all client-side routing. Every non-API path serves the
| same blade shell so the app can boot and take over navigation.
|
*/

// Google OAuth (Laravel Socialite + Sanctum token issued to the SPA).
Route::get('/auth/google', [AuthController::class, 'redirectToGoogle'])->name('google.redirect');
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('google.callback');
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('auth.logout');

// Tablón reply landing: signed link (7-day expiry) from the contact email.
// Validates the signature, then hands off to the SPA which renders the reply
// form for the (logged-in) announcement owner.
Route::get('/tablon/responder/{contacto}', fn () => view('app'))
    ->middleware('signed')
    ->name('tablon.responder');

// SPA shell for every non-API, non-auth path.
Route::view('/{any?}', 'app')
    ->where('any', '^(?!api|auth/google|auth/logout).*$');
