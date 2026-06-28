<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\StripeWebhookController;
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

// Social OAuth (Laravel Socialite + Sanctum token issued to the SPA). Generic
// across providers; each is only usable when its credentials are configured.
Route::get('/auth/{provider}', [AuthController::class, 'redirect'])
    ->whereIn('provider', ['google', 'microsoft'])
    ->name('oauth.redirect');
Route::match(['get', 'post'], '/auth/{provider}/callback', [AuthController::class, 'callback'])
    ->whereIn('provider', ['google', 'microsoft'])
    ->name('oauth.callback');
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('auth.logout');

// Stripe webhook: server-to-server POST, signature-verified, CSRF-exempt.
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])->name('stripe.webhook');

// Tablón reply landing: signed link (7-day expiry) from the contact email.
// Validates the signature, then hands off to the SPA which renders the reply
// form for the (logged-in) announcement owner.
Route::get('/tablon/responder/{contacto}', fn () => view('app'))
    ->middleware('signed')
    ->name('tablon.responder');

// SPA shell for every non-API, non-auth path.
Route::view('/{any?}', 'app')
    ->where('any', '^(?!api|auth/).*$');
