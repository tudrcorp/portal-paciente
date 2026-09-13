<?php

namespace App\Services\Geo\Drivers\Google;

use App\Services\Geo\Contracts\ReverseGeocoder;
use App\Services\Geo\Data\Coordinates;
use App\Services\Geo\Support\GeoHttp;
use Illuminate\Support\Facades\Log;
use Throwable;

class GoogleReverseGeocoder implements ReverseGeocoder
{
    public function reverse(Coordinates $coordinates): ?string
    {
        $key = (string) config('geolocation.google.key');

        if ($key === '') {
            return null;
        }

        $base = rtrim((string) config('geolocation.google.geocode_base'), '/');

        try {
            $response = GeoHttp::client()->get("{$base}/json", [
                'latlng' => $coordinates->lat.','.$coordinates->lng,
                'language' => (string) config('geolocation.google.language', 'es'),
                'result_type' => 'street_address|route|neighborhood|locality',
                'key' => $key,
            ]);

            if ($response->failed()) {
                return null;
            }

            return $response->json('results.0.formatted_address') ?: null;
        } catch (Throwable $e) {
            Log::warning('[geo] Geocoding de Google falló: '.$e->getMessage());

            return null;
        }
    }
}
