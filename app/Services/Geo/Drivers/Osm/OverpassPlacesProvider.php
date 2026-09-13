<?php

namespace App\Services\Geo\Drivers\Osm;

use App\Services\Geo\Contracts\PlacesProvider;
use App\Services\Geo\Data\Coordinates;
use App\Services\Geo\Data\Place;
use App\Services\Geo\GeoException;
use App\Services\Geo\Support\GeoHttp;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Búsqueda de centros de salud sobre OpenStreetMap vía Overpass API.
 *
 * Gratis y sin API key, pero los servidores públicos son de uso comunitario:
 * por eso una sola consulta combinada por petición, resultados acotados y
 * caché agresiva aguas arriba (ver NearbyPlacesController).
 */
class OverpassPlacesProvider implements PlacesProvider
{
    public function search(Coordinates $center, array $categories, int $radius, int $limit): array
    {
        $query = $this->buildQuery($center, $categories, $radius, $limit);
        $elements = $this->execute($query);

        $places = [];
        $seen = [];

        foreach ($elements as $element) {
            $place = $this->toPlace($element, $categories);

            if ($place === null) {
                continue;
            }

            // Overpass devuelve el mismo centro como nodo y como vía cuando el
            // edificio está mapeado dos veces; deduplicamos por nombre+posición.
            $fingerprint = mb_strtolower($place->name).'@'.$place->coordinates->rounded(4);

            if (isset($seen[$fingerprint])) {
                continue;
            }

            $seen[$fingerprint] = true;

            $distance = $center->distanceTo($place->coordinates);

            if ($distance > $radius) {
                continue;
            }

            $places[] = $place->withDistance($distance);
        }

        usort($places, fn (Place $a, Place $b) => $a->distance <=> $b->distance);

        return array_slice($places, 0, $limit);
    }

    /**
     * @param  array<int, string>  $categories
     */
    private function buildQuery(Coordinates $center, array $categories, int $radius, int $limit): string
    {
        $configured = (array) config('geolocation.categories', []);
        $clauses = [];

        $lat = number_format($center->lat, 6, '.', '');
        $lng = number_format($center->lng, 6, '.', '');

        foreach ($categories as $category) {
            foreach ($configured[$category]['overpass'] ?? [] as $filters) {
                $selector = '';

                foreach ($filters as $key => $value) {
                    $selector .= '["'.$this->escape($key).'"="'.$this->escape($value).'"]';
                }

                // nwr = nodes + ways + relations: un hospital suele estar
                // mapeado como polígono, no como punto.
                $clauses[] = "nwr{$selector}(around:{$radius},{$lat},{$lng});";
            }
        }

        if ($clauses === []) {
            throw new GeoException('No hay categorías válidas para consultar.');
        }

        $timeout = (int) config('geolocation.http.timeout', 12) + 8;
        $body = implode('', $clauses);

        // «out center» entrega el centroide de vías y relaciones, que es justo
        // lo que necesitamos para pintar un pin.
        return "[out:json][timeout:{$timeout}];({$body});out center tags {$limit};";
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function execute(string $query): array
    {
        $endpoints = (array) config('geolocation.osm.overpass_endpoints', []);
        $lastError = null;

        foreach ($endpoints as $endpoint) {
            try {
                $response = GeoHttp::client()->asForm()->post($endpoint, ['data' => $query]);

                if ($response->failed()) {
                    $lastError = "Overpass respondió {$response->status()} en {$endpoint}";
                    Log::warning('[geo] '.$lastError);

                    continue;
                }

                $elements = $response->json('elements');

                if (! is_array($elements)) {
                    $lastError = "Overpass devolvió una carga inesperada en {$endpoint}";

                    continue;
                }

                return $elements;
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
                Log::warning('[geo] Overpass falló en '.$endpoint.': '.$lastError);
            }
        }

        throw new GeoException('No se pudo consultar el mapa de centros de salud. '.($lastError ?? ''));
    }

    /**
     * @param  array<string, mixed>  $element
     * @param  array<int, string>  $categories
     */
    private function toPlace(array $element, array $categories): ?Place
    {
        $tags = (array) ($element['tags'] ?? []);

        $lat = $element['lat'] ?? ($element['center']['lat'] ?? null);
        $lng = $element['lon'] ?? ($element['center']['lon'] ?? null);

        if ($lat === null || $lng === null) {
            return null;
        }

        $category = $this->resolveCategory($tags, $categories);

        if ($category === null) {
            return null;
        }

        $label = (string) config("geolocation.categories.{$category}.label", 'Centro de salud');
        $name = trim((string) ($tags['name'] ?? $tags['operator'] ?? ''));

        return new Place(
            id: ($element['type'] ?? 'node').'/'.($element['id'] ?? uniqid()),
            // Muchos POI de Venezuela están sin nombre: mejor mostrarlos con la
            // categoría que descartarlos y dejar el mapa vacío.
            name: $name !== '' ? $name : rtrim($label, 's'),
            category: $category,
            coordinates: Coordinates::make($lat, $lng),
            address: $this->composeAddress($tags),
            phone: $this->firstTag($tags, ['phone', 'contact:phone', 'contact:mobile']),
            openingHours: $this->firstTag($tags, ['opening_hours']),
            website: $this->firstTag($tags, ['website', 'contact:website']),
            emergency: isset($tags['emergency']) ? $tags['emergency'] === 'yes' : null,
        );
    }

    /**
     * @param  array<string, mixed>  $tags
     * @param  array<int, string>  $categories
     */
    private function resolveCategory(array $tags, array $categories): ?string
    {
        $configured = (array) config('geolocation.categories', []);

        // Se respeta el orden pedido por el usuario: un «hospital» etiquetado
        // también como «clinic» se clasifica por la primera coincidencia.
        foreach ($categories as $category) {
            foreach ($configured[$category]['overpass'] ?? [] as $filters) {
                foreach ($filters as $key => $value) {
                    if (($tags[$key] ?? null) === $value) {
                        return $category;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $tags
     */
    private function composeAddress(array $tags): ?string
    {
        $street = trim((string) ($tags['addr:street'] ?? ''));
        $number = trim((string) ($tags['addr:housenumber'] ?? ''));
        $city = trim((string) ($tags['addr:city'] ?? ''));

        $line = trim($street.($number !== '' ? ' '.$number : ''));
        $parts = array_values(array_filter([$line, $city], fn ($part) => $part !== ''));

        if ($parts === []) {
            return $this->firstTag($tags, ['addr:full', 'address']);
        }

        return implode(', ', $parts);
    }

    /**
     * @param  array<string, mixed>  $tags
     * @param  array<int, string>  $keys
     */
    private function firstTag(array $tags, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($tags[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }
}
