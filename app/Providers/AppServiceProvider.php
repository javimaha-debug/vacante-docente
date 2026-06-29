<?php

namespace App\Providers;

use App\Models\UserList;
use App\Services\GoogleMapsService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\Provider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(GoogleMapsService::class, function () {
            return new GoogleMapsService(config('services.google_maps.key'));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register the Microsoft Socialite provider when the package is present
        // (composer require socialite-providers/microsoft). Guarded so the app
        // boots fine before it's installed.
        if (class_exists(Provider::class)) {
            Event::listen(function (SocialiteWasCalled $event) {
                $event->extendSocialite('microsoft', Provider::class);
            });
        }

        // Login: throttle by email+IP (5/min) and by IP (20/min) to slow down
        // credential stuffing / brute force.
        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email');

            return [
                Limit::perMinute(5)->by(mb_strtolower($email).'|'.$request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        // Public listings with personal data (participants): cap requests per IP
        // to hinder bulk scraping while keeping the list browsable.
        RateLimiter::for('public-list', function (Request $request) {
            return Limit::perMinute(40)->by($request->ip());
        });

        // Geocoding: 20 requests / minute / IP.
        RateLimiter::for('geocode', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        // Distance Matrix: the explorer computes distances in chunks and loops
        // until done, so allow enough requests/min/IP to finish a full list.
        RateLimiter::for('distances', function (Request $request) {
            return Limit::perMinute(40)->by($request->ip());
        });

        // AI assistant: paid LLM calls. Per-minute cap per user on top of the
        // existing per-day cap, so a script can't burn the budget in seconds.
        RateLimiter::for('ai', function (Request $request) {
            return Limit::perMinute(10)->by((string) ($request->user()?->id ?? $request->ip()));
        });

        // Flashcard/simulacro generation is heavier — tighter per-minute cap.
        RateLimiter::for('ai-generate', function (Request $request) {
            return Limit::perMinute(5)->by((string) ($request->user()?->id ?? $request->ip()));
        });

        // Document uploads/imports spawn processing jobs — cap per hour per user.
        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perHour(20)->by((string) ($request->user()?->id ?? $request->ip()));
        });

        // School directory search — DoS guard that still allows fluid browsing.
        RateLimiter::for('centros', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        // Scope the {userList} route binding to its owner so one session can't
        // read or mutate another's list (and home address) by guessing its
        // sequential id (IDOR — audit finding SEC-C1). Ownership is proven by
        // EITHER the anonymous secret session_token (header or request field,
        // compared in constant time) OR an authenticated account that owns the
        // list (user_id). These routes carry no auth:sanctum middleware, so the
        // bearer token is resolved via the sanctum guard explicitly.
        Route::bind('userList', function ($value) {
            $list = UserList::findOrFail($value);

            $token = request()->header('X-Session-Token') ?: request()->input('session_token');
            $tokenOwner = is_string($token) && $token !== ''
                && $list->session_token !== null
                && hash_equals((string) $list->session_token, $token);

            $user = auth('sanctum')->user();
            $accountOwner = $user && $list->user_id !== null && (int) $list->user_id === (int) $user->id;

            abort_unless($tokenOwner || $accountOwner, 403, 'No autorizado para esta lista.');

            return $list;
        });
    }
}
