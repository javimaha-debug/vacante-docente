<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| The React SPA owns all client-side routing. Every non-API path serves the
| same blade shell so the app can boot and take over navigation.
|
| Phase 2 (Socialite/Google) — uncomment to enable (see GoogleAuthController):
|   use App\Http\Controllers\Auth\GoogleAuthController;
|   Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
|   Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');
|
*/

Route::view('/{any?}', 'app')
    ->where('any', '^(?!api).*$');
