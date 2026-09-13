<?php

namespace App\Services\Geo\Drivers\Google;

use App\Services\Geo\Contracts\PlacesProvider;
use App\Services\Geo\Data\Coordinates;
use App\Services\Geo\Data\Place;
use App\Services\Geo\GeoException;
use App\Services\Geo\Support\GeoHttp;

/**
 * Places API (New) — Nearby Search.
 *
 * Se activa con GEO_DRIVER=google y GOOGLE_MAPS_KEY. Devuelve exactamente la
 * misma forma de datos que el driver de OpenStreetMap, de modo que el cambio
 * de proveedor no toca ni el controlador ni la interfaz.
 */
class GooglePlacesProvider implements PlacesProvider
{
    /** Nearby Search topa en 20 resultados por petición. */
    private const PER_REQUEST = 20;

    private const FIELD_MASK = 'places.id,places.displayName,places.formattedAddress,places.location,'
        .'places.nationalPhoneNumber,places.regularOpeningHours.openNow,places.websiteUri,places.primaryType';

    public function search(Coordinates $center, array $categories, int $radius, int $limit): array
    {
        $key = (string) config('geolocation.google.key');

        if ($key === '') {
            throw new GeoException('Falta GOOGLE_MAPS_KEY para usar el proveedor de Google.');
        }

        $places = [];
        $seen = [];

        // Una petición por categoría: Nearby Search ordena por distancia dentro
        // de cada llamada, y así el cupo de 20 no se lo come una sola categoría.
        foreach ($categories as $category) {
            foreach ($this->searchCategory($center, $category, $radius, $key) as $place) {
                if (isset($seen[$place->id])) {
                    continue;
                }

                $seen[$place->id] = true;
                $places[] = $place->withDistance($center->distanceTo($place->coordinates));
            }
        }

        usort($places, fn (Place $a, Place $b) => $a->distance <=> $b->distance);

        return array_slice($places, 0, $limit);
    }

    /**
     * @return array<int, Place>
     */
    private function searchCategory(Coordinates $center, string $category, int $radius, string $key): array
    {
        $types = (array) config("geolocation.categories.{$category}.google_types", []);

        if ($types === []) {
            return [];
        }

        $base = rtrim((string) config('geolocation.google.places_base'), '/');

        $response = GeoHttp::client([
            'X-Goog-Api-Key' => $key,
            'X-Goog-FieldMask' => self::FIELD_MASK,
        ])->post("{$base}/places:searchNearby", [
            'includedTypes' => array_values($types),
            'maxResultCount' => self::PER_REQUEST,
            'rankPreference' => 'DISTANCE',
            'languageCode' => (string) config('geolocation.google.language', 'es'),
            'regionCode' => strtoupper((string) config('geolocation.google.region', 've')),
            'locationRestriction' => [
                'circle' => [
                    'center' => ['latitude' => $center->lat, 'longitude' => $center->lng],
                    'radius' => (float) $radius,
                ],
            ],
        ]);

        if ($response->failed()) {
            throw new GeoException('Places API respondió '.$response->status().': '.$response->json('error.message', ''));
        }

        $places = [];

        foreach ((array) $response->json('places', []) as $item) {
            $lat = $item['location']['latitude'] ?? null;
            $lng = $item['location']['longitude'] ?? null;

            if ($lat === null || $lng === null) {
                continue;
            }

            $places[] = new Place(
                id: (string) ($item['id'] ?? uniqid('g_')),
                name: (string) ($item['displayName']['text'] ?? config("geolocation.categories.{$category}.label", 'Centro de salud')),
                category: $category,
                coordinates: Coordinates::make($lat, $lng),
                address: $item['formattedAddress'] ?? null,
                phone: $item['nationalPhoneNumber'] ?? null,
                openingHours: isset($item['regularOpeningHours']['openNow'])
                    ? ($item['regularOpeningHours']['openNow'] ? 'Abierto ahora' : 'Cerrado ahora')
                    : null,
                website: $item['websiteUri'] ?? null,
                emergency: null,
            );
        }

        return $places;
    }
}
