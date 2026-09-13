<?php

namespace App\Services\Geo\Drivers\Google;

use App\Services\Geo\Contracts\RoutingProvider;
use App\Services\Geo\Data\Coordinates;
use App\Services\Geo\Data\RouteResult;
use App\Services\Geo\GeoException;
use App\Services\Geo\Support\GeoHttp;
use App\Services\Geo\Support\Polyline;

/**
 * Routes API (computeRoutes). A diferencia de OSRM, considera el tráfico en
 * tiempo real para el perfil en vehículo.
 */
class GoogleRoutingProvider implements RoutingProvider
{
    private const FIELD_MASK = 'routes.duration,routes.distanceMeters,routes.polyline.encodedPolyline';

    public function route(Coordinates $from, Coordinates $to, string $profile): RouteResult
    {
        $key = (string) config('geolocation.google.key');

        if ($key === '') {
            throw new GeoException('Falta GOOGLE_MAPS_KEY para usar el proveedor de Google.');
        }

        $base = rtrim((string) config('geolocation.google.routes_base'), '/');
        $driving = $profile !== 'walking';

        $payload = [
            'origin' => ['location' => ['latLng' => ['latitude' => $from->lat, 'longitude' => $from->lng]]],
            'destination' => ['location' => ['latLng' => ['latitude' => $to->lat, 'longitude' => $to->lng]]],
            'travelMode' => $driving ? 'DRIVE' : 'WALK',
            'polylineQuality' => 'HIGH_QUALITY',
            'languageCode' => (string) config('geolocation.google.language', 'es'),
            'units' => 'METRIC',
        ];

        // routingPreference solo es válido en modo vehículo.
        if ($driving) {
            $payload['routingPreference'] = 'TRAFFIC_AWARE';
        }

        $response = GeoHttp::client([
            'X-Goog-Api-Key' => $key,
            'X-Goog-FieldMask' => self::FIELD_MASK,
        ])->post("{$base}/directions/v2:computeRoutes", $payload);

        if ($response->failed()) {
            throw new GeoException('Routes API respondió '.$response->status().': '.$response->json('error.message', ''));
        }

        $route = $response->json('routes.0');

        if (! is_array($route)) {
            throw new GeoException('Routes API no devolvió ninguna ruta.');
        }

        $geometry = Polyline::decode((string) ($route['polyline']['encodedPolyline'] ?? ''), 5);

        if ($geometry === []) {
            throw new GeoException('Routes API devolvió una geometría vacía.');
        }

        return new RouteResult(
            geometry: $geometry,
            distance: (float) ($route['distanceMeters'] ?? 0),
            // La duración llega como «123s».
            duration: (float) rtrim((string) ($route['duration'] ?? '0s'), 's'),
            profile: $profile,
            provider: 'google',
        );
    }
}
