<?php

namespace App\Providers;

use App\Services\GoogleMapsService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        // Geocoding: 20 requests / minute / IP.
        RateLimiter::for('geocode', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        // Distance Matrix: 5 requests / minute / IP (each request fans out to many centres).
        RateLimiter::for('distances', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
