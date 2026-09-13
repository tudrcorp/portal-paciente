<?php

namespace App\Providers;

use App\Auth\TelemedicinePatientUserProvider;
use App\Services\Geo\Contracts\PlacesProvider;
use App\Services\Geo\Contracts\ReverseGeocoder;
use App\Services\Geo\Contracts\RoutingProvider;
use App\Services\Geo\Drivers\Google\GooglePlacesProvider;
use App\Services\Geo\Drivers\Google\GoogleReverseGeocoder;
use App\Services\Geo\Drivers\Google\GoogleRoutingProvider;
use App\Services\Geo\Drivers\Osm\NominatimReverseGeocoder;
use App\Services\Geo\Drivers\Osm\OpenStreetMapRoutingProvider;
use App\Services\Geo\Drivers\Osm\OverpassPlacesProvider;
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

        $this->registerGeolocationDrivers();
    }

    /**
     * Enlaza los contratos de geolocalización al proveedor elegido en
     * config/geolocation.php. Cambiar de OpenStreetMap a Google es una
     * variable de entorno: ni los controladores ni la interfaz se enteran.
     */
    private function registerGeolocationDrivers(): void
    {
        // El driver se lee al resolver, no al registrar: así un cambio de
        // configuración en caliente (o en un test) elige el proveedor correcto.
        $usesGoogle = fn () => config('geolocation.driver') === 'google';

        $this->app->bind(
            PlacesProvider::class,
            fn () => $usesGoogle() ? new GooglePlacesProvider : new OverpassPlacesProvider
        );

        $this->app->bind(
            RoutingProvider::class,
            fn () => $usesGoogle() ? new GoogleRoutingProvider : new OpenStreetMapRoutingProvider
        );

        $this->app->bind(
            ReverseGeocoder::class,
            fn () => $usesGoogle() ? new GoogleReverseGeocoder : new NominatimReverseGeocoder
        );
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
