<?php

namespace App\Providers;

use App\Services\Tmdb\TmdbClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TmdbClient::class, fn () => TmdbClient::fromConfig());
    }

    public function boot(): void
    {
        // The demo API is public, so slow down password guessing:
        // 10 attempts per minute per email+IP, and 30 per minute per IP overall.
        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(10)->by(Str::lower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(30)->by($request->ip()),
        ]);

        // Keep the default { "data": ... } wrapper on every resource.
        JsonResource::wrap('data');
    }
}
