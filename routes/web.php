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

// SPA shell for every non-API, non-auth path.
Route::view('/{any?}', 'app')
    ->where('any', '^(?!api|auth/google|auth/logout).*$');
