<?php

namespace App\Services\Geo\Drivers\Osm;

use App\Services\Geo\Contracts\ReverseGeocoder;
use App\Services\Geo\Data\Coordinates;
use App\Services\Geo\Support\GeoHttp;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Dirección legible del punto donde está el paciente, vía Nominatim.
 *
 * Es información de apoyo: si el servicio no responde, la pantalla sigue
 * funcionando con las coordenadas, así que aquí nunca se lanza excepción.
 */
class NominatimReverseGeocoder implements ReverseGeocoder
{
    public function reverse(Coordinates $coordinates): ?string
    {
        $base = rtrim((string) config('geolocation.osm.nominatim_base'), '/');

        try {
            $response = GeoHttp::client()->get("{$base}/reverse", [
                'format' => 'jsonv2',
                'lat' => $coordinates->lat,
                'lon' => $coordinates->lng,
                'zoom' => 17,
                'addressdetails' => 1,
                'accept-language' => 'es',
            ]);

            if ($response->failed()) {
                return null;
            }

            return $this->compose((array) $response->json('address', []))
                ?? ($response->json('display_name') ?: null);
        } catch (Throwable $e) {
            Log::warning('[geo] Nominatim falló: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Nominatim devuelve direcciones larguísimas; para la cabecera del panel
     * basta con calle + sector + ciudad.
     *
     * @param  array<string, mixed>  $address
     */
    private function compose(array $address): ?string
    {
        $street = $address['road'] ?? $address['pedestrian'] ?? $address['footway'] ?? null;
        $area = $address['neighbourhood'] ?? $address['suburb'] ?? $address['quarter'] ?? $address['village'] ?? null;
        $city = $address['city'] ?? $address['town'] ?? $address['municipality'] ?? $address['state'] ?? null;

        $parts = [];

        foreach ([$street, $area, $city] as $part) {
            $value = trim((string) ($part ?? ''));

            if ($value !== '' && ! in_array($value, $parts, true)) {
                $parts[] = $value;
            }
        }

        return $parts === [] ? null : implode(', ', $parts);
    }
}
