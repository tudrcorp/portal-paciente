<?php

namespace App\Services\Geo;

use App\Services\Geo\Contracts\PlacesProvider;
use App\Services\Geo\Contracts\ReverseGeocoder;
use App\Services\Geo\Contracts\RoutingProvider;
use App\Services\Geo\Data\Coordinates;
use App\Services\Geo\Data\Place;
use App\Services\Geo\Data\RouteResult;
use Illuminate\Support\Facades\Cache;

/**
 * Fachada de la geolocalización: añade caché sobre el proveedor activo.
 *
 * La caché es la pieza que hace viable el modo gratuito (los servidores
 * públicos de OSM son comunitarios) y la que abarata el modo Google, donde
 * cada consulta se factura. Las claves se construyen con coordenadas
 * redondeadas para que el temblor del GPS no invalide todo a cada segundo.
 */
class NearbyLocator
{
    public function __construct(
        private readonly PlacesProvider $places,
        private readonly RoutingProvider $routing,
        private readonly ReverseGeocoder $geocoder,
    ) {}

    /**
     * @param  array<int, string>  $categories
     * @return array<int, Place>
     */
    public function places(Coordinates $center, array $categories, int $radius): array
    {
        sort($categories);

        $limit = (int) config('geolocation.max_results', 60);

        // ~110 m de rejilla: suficiente para que dos pacientes en la misma
        // cuadra compartan resultado, sin falsear las distancias mostradas.
        $key = 'geo:places:'.config('geolocation.driver')
            .':'.$center->rounded(3)
            .':'.$radius
            .':'.implode('|', $categories);

        /** @var array<int, array<string, mixed>> $cached */
        $cached = Cache::remember(
            $key,
            (int) config('geolocation.cache.places_ttl', 21600),
            fn () => array_map(
                fn (Place $place) => $place->toArray(),
                $this->places->search($center, $categories, $radius, $limit)
            )
        );

        // La distancia se recalcula contra la posición real del usuario, no
        // contra el centro redondeado de la clave de caché.
        return array_map(function (array $item) use ($center) {
            $coordinates = Coordinates::make($item['lat'], $item['lng']);

            return [...$item, 'distance' => (int) round($center->distanceTo($coordinates))];
        }, $this->sortByDistance($cached, $center));
    }

    public function route(Coordinates $from, Coordinates $to, string $profile): RouteResult
    {
        $key = 'geo:route:'.config('geolocation.driver')
            .':'.$profile
            .':'.$from->rounded(4)
            .':'.$to->rounded(5);

        $cached = Cache::remember(
            $key,
            (int) config('geolocation.cache.route_ttl', 3600),
            fn () => $this->routing->route($from, $to, $profile)->toArray()
        );

        return new RouteResult(
            geometry: $cached['geometry'],
            distance: (float) $cached['distance'],
            duration: (float) $cached['duration'],
            profile: $cached['profile'],
            provider: $cached['provider'],
        );
    }

    public function address(Coordinates $coordinates): ?string
    {
        $key = 'geo:address:'.config('geolocation.driver').':'.$coordinates->rounded(4);

        return Cache::remember(
            $key,
            (int) config('geolocation.cache.geocode_ttl', 86400),
            fn () => $this->geocoder->reverse($coordinates)
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $places
     * @return array<int, array<string, mixed>>
     */
    private function sortByDistance(array $places, Coordinates $center): array
    {
        usort($places, function (array $a, array $b) use ($center) {
            $distanceA = $center->distanceTo(Coordinates::make($a['lat'], $a['lng']));
            $distanceB = $center->distanceTo(Coordinates::make($b['lat'], $b['lng']));

            return $distanceA <=> $distanceB;
        });

        return $places;
    }
}
