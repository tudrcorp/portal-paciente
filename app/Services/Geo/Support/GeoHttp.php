<?php

namespace App\Services\Geo\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP compartido por los drivers: fija el User-Agent que exige la
 * política de uso de OSM y unos timeouts cortos, porque una pantalla de mapa
 * que se cuelga esperando a un tercero es peor que una sin resultados.
 */
final class GeoHttp
{
    public static function client(array $headers = []): PendingRequest
    {
        return Http::withHeaders(array_merge([
            'User-Agent' => (string) config('geolocation.user_agent'),
            'Accept-Language' => 'es',
        ], $headers))
            ->timeout((int) config('geolocation.http.timeout', 12))
            ->connectTimeout((int) config('geolocation.http.connect_timeout', 5));
    }
}
