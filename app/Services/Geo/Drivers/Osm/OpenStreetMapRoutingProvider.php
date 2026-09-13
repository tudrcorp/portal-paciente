<?php

namespace App\Services\Geo\Drivers\Osm;

use App\Services\Geo\Contracts\RoutingProvider;
use App\Services\Geo\Data\Coordinates;
use App\Services\Geo\Data\RouteResult;
use App\Services\Geo\GeoException;
use App\Services\Geo\Support\GeoHttp;
use App\Services\Geo\Support\Polyline;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ruteo gratuito sobre datos de OpenStreetMap.
 *
 * El demo público de OSRM solo sirve el perfil de vehículo (los perfiles a pie
 * y bici fueron retirados), así que el trayecto peatonal se pide a la instancia
 * pública de Valhalla de FOSSGIS, que sí distingue perfiles. Cada motor actúa
 * además como respaldo del otro.
 */
class OpenStreetMapRoutingProvider implements RoutingProvider
{
    public function route(Coordinates $from, Coordinates $to, string $profile): RouteResult
    {
        $attempts = $profile === 'walking'
            ? [fn () => $this->viaValhalla($from, $to, $profile), fn () => $this->viaOsrm($from, $to, $profile)]
            : [fn () => $this->viaOsrm($from, $to, $profile), fn () => $this->viaValhalla($from, $to, $profile)];

        $lastError = null;

        foreach ($attempts as $attempt) {
            try {
                return $attempt();
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
                Log::warning('[geo] Ruteo fallido: '.$lastError);
            }
        }

        throw new GeoException('No se pudo calcular la ruta. '.($lastError ?? ''));
    }

    private function viaOsrm(Coordinates $from, Coordinates $to, string $profile): RouteResult
    {
        $base = rtrim((string) config('geolocation.osm.osrm_base'), '/');
        $coords = $this->pair($from).';'.$this->pair($to);

        $response = GeoHttp::client()->get("{$base}/route/v1/driving/{$coords}", [
            'overview' => 'full',
            'geometries' => 'geojson',
            'alternatives' => 'false',
            'steps' => 'false',
        ]);

        if ($response->failed() || $response->json('code') !== 'Ok') {
            throw new GeoException('OSRM respondió '.$response->status().' ('.$response->json('code', 'sin código').')');
        }

        $route = $response->json('routes.0');

        if (! is_array($route)) {
            throw new GeoException('OSRM no devolvió ninguna ruta.');
        }

        // GeoJSON viene como [lon, lat]; Leaflet espera [lat, lng].
        $geometry = array_map(
            fn (array $point) => [(float) $point[1], (float) $point[0]],
            (array) ($route['geometry']['coordinates'] ?? [])
        );

        if ($geometry === []) {
            throw new GeoException('OSRM devolvió una geometría vacía.');
        }

        return new RouteResult(
            geometry: $geometry,
            distance: (float) ($route['distance'] ?? 0),
            duration: (float) ($route['duration'] ?? 0),
            profile: $profile,
            provider: 'osrm',
        );
    }

    private function viaValhalla(Coordinates $from, Coordinates $to, string $profile): RouteResult
    {
        $base = rtrim((string) config('geolocation.osm.valhalla_base'), '/');
        $costing = $profile === 'walking' ? 'pedestrian' : 'auto';

        $response = GeoHttp::client()->post("{$base}/route", [
            'locations' => [
                ['lat' => $from->lat, 'lon' => $from->lng],
                ['lat' => $to->lat, 'lon' => $to->lng],
            ],
            'costing' => $costing,
            'directions_options' => ['units' => 'kilometers', 'language' => 'es-ES'],
        ]);

        if ($response->failed()) {
            throw new GeoException('Valhalla respondió '.$response->status());
        }

        $legs = (array) $response->json('trip.legs', []);
        $geometry = [];

        foreach ($legs as $leg) {
            foreach (Polyline::decode((string) ($leg['shape'] ?? ''), 6) as $point) {
                $geometry[] = $point;
            }
        }

        if ($geometry === []) {
            throw new GeoException('Valhalla devolvió una geometría vacía.');
        }

        return new RouteResult(
            geometry: $geometry,
            // El resumen viene en kilómetros por directions_options.
            distance: (float) $response->json('trip.summary.length', 0) * 1000,
            duration: (float) $response->json('trip.summary.time', 0),
            profile: $profile,
            provider: 'valhalla',
        );
    }

    private function pair(Coordinates $point): string
    {
        return number_format($point->lng, 6, '.', '').','.number_format($point->lat, 6, '.', '');
    }
}
