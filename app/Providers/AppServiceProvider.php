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
        if (class_exists(\SocialiteProviders\Microsoft\Provider::class)) {
            Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
                $event->extendSocialite('microsoft', \SocialiteProviders\Microsoft\Provider::class);
            });
        }

        // Geocoding: 20 requests / minute / IP.
        RateLimiter::for('geocode', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        // Distance Matrix: the explorer computes distances in chunks and loops
        // until done, so allow enough requests/min/IP to finish a full list.
        RateLimiter::for('distances', function (Request $request) {
            return Limit::perMinute(40)->by($request->ip());
        });

        // Scope the anonymous {userList} route binding to the caller's
        // session_token (header or request field) so one session can't read or
        // mutate another's list by guessing its sequential id (IDOR).
        Route::bind('userList', function ($value) {
            $list = UserList::findOrFail($value);
            $token = request()->header('X-Session-Token') ?: request()->input('session_token');

            abort_unless(
                is_string($token) && $token !== '' && hash_equals((string) $list->session_token, $token),
                403,
                'No autorizado para esta lista.'
            );

            return $list;
        });
    }
}
