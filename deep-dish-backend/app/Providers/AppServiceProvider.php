<?php

namespace App\Providers;

use App\Broadcasting\BroadcasterTolerante;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Support\Facades\Broadcast;
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
        // Mesmo driver que o Laravel monta para 'reverb', so que tolerante a queda
        // do servidor quando a fila e sync. Ver BroadcasterTolerante.
        Broadcast::extend('reverb', fn ($app, array $config) => new BroadcasterTolerante(
            $app->make(BroadcastManager::class)->pusher($config),
            $config['jsonp'] ?? false,
        ));
    }
}
