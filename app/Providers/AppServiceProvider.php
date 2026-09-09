<?php

namespace App\Providers;

use App\Auth\TelemedicinePatientUserProvider;
use App\Services\PortalApi\PortalApiClient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Cliente HTTP reutilizable hacia portal-paciente-api.
        $this->app->singleton(PortalApiClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::provider('telemedicine_eloquent', function ($app, array $config) {
            return new TelemedicinePatientUserProvider($app['hash'], $config['model']);
        });
    }
}

