<?php

namespace App\Providers;

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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Teto geral da API. O throttleApi() no bootstrap aponta para este
        // limitador 'api' — que o Laravel 11 não define sozinho. Sem ele, a rota
        // com 'throttle:api' estoura MissingRateLimiterException (500).
        // Por usuário quando autenticado; por IP quando não.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->getAuthIdentifier() ?: $request->ip()));
    }
}
