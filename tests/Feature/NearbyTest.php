<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // La capa de geolocalización cachea por coordenada: sin vaciarla, una
    // prueba heredaría la respuesta de la anterior.
    Cache::flush();

    config()->set('geolocation.driver', 'osm');
});

function overpassResponse(array $elements): array
{
    return ['version' => 0.6, 'elements' => $elements];
}

test('los invitados no pueden ver el mapa de centros cercanos', function () {
    $this->get(route('nearby.index'))->assertRedirect(route('login'));
});

test('el paciente autenticado ve la pantalla con su configuración', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('nearby.index'))
        ->assertOk()
        ->assertSee('data-geo-root', false)
        ->assertSee('Radio de búsqueda', false)
        ->assertSee('Farmacias');
});

test('la búsqueda devuelve los lugares ordenados por cercanía', function () {
    Http::fake([
        '*overpass*' => Http::response(overpassResponse([
            // El lejano llega primero a propósito: el orden lo impone el portal.
            [
                'type' => 'node',
                'id' => 2,
                'lat' => 10.5100,
                'lon' => -66.9000,
                'tags' => ['amenity' => 'pharmacy', 'name' => 'Farmacia Lejana'],
            ],
            [
                'type' => 'node',
                'id' => 1,
                'lat' => 10.5010,
                'lon' => -66.9000,
                'tags' => [
                    'amenity' => 'pharmacy',
                    'name' => 'Farmacia Cercana',
                    'addr:street' => 'Av. Principal',
                    'addr:city' => 'Caracas',
                    'phone' => '+58 212 1234567',
                ],
            ],
        ])),
    ]);

    $this->actingAs(User::factory()->create());

    $response = $this->getJson(route('nearby.places', [
        'lat' => 10.5,
        'lng' => -66.9,
        'radius' => 5000,
        'categories' => ['pharmacy'],
    ]))->assertOk();

    expect($response->json('count'))->toBe(2)
        ->and($response->json('places.0.name'))->toBe('Farmacia Cercana')
        ->and($response->json('places.0.address'))->toBe('Av. Principal, Caracas')
        ->and($response->json('places.0.phone'))->toBe('+58 212 1234567')
        ->and($response->json('places.1.name'))->toBe('Farmacia Lejana')
        ->and($response->json('places.0.distance'))->toBeLessThan($response->json('places.1.distance'));
});

test('los resultados fuera del radio se descartan', function () {
    Http::fake([
        '*overpass*' => Http::response(overpassResponse([
            [
                'type' => 'node',
                'id' => 3,
                // ~11 km al norte: fuera de un radio de 5 km.
                'lat' => 10.6000,
                'lon' => -66.9000,
                'tags' => ['amenity' => 'hospital', 'name' => 'Hospital Remoto'],
            ],
        ])),
    ]);

    $this->actingAs(User::factory()->create());

    $this->getJson(route('nearby.places', [
        'lat' => 10.5,
        'lng' => -66.9,
        'radius' => 5000,
        'categories' => ['hospital'],
    ]))->assertOk()->assertJsonPath('count', 0);
});

test('un centro sin nombre se muestra con su categoría', function () {
    Http::fake([
        '*overpass*' => Http::response(overpassResponse([
            [
                'type' => 'way',
                'id' => 9,
                'center' => ['lat' => 10.5005, 'lon' => -66.9005],
                'tags' => ['amenity' => 'clinic'],
            ],
        ])),
    ]);

    $this->actingAs(User::factory()->create());

    $this->getJson(route('nearby.places', [
        'lat' => 10.5,
        'lng' => -66.9,
        'radius' => 5000,
        'categories' => ['clinic'],
    ]))->assertOk()->assertJsonPath('places.0.name', 'Clínica');
});

test('la búsqueda valida las categorías recibidas', function () {
    $this->actingAs(User::factory()->create());

    $this->getJson(route('nearby.places', [
        'lat' => 10.5,
        'lng' => -66.9,
        'radius' => 5000,
        'categories' => ['casino'],
    ]))->assertStatus(422);
});

test('la búsqueda avisa cuando el proveedor no responde', function () {
    Http::fake(['*overpass*' => Http::response('', 504)]);

    $this->actingAs(User::factory()->create());

    $this->getJson(route('nearby.places', [
        'lat' => 10.5,
        'lng' => -66.9,
        'radius' => 5000,
        'categories' => ['pharmacy'],
    ]))->assertStatus(503)->assertJsonStructure(['message']);
});

test('la ruta en vehículo se traza con OSRM', function () {
    Http::fake([
        '*project-osrm*' => Http::response([
            'code' => 'Ok',
            'routes' => [[
                'distance' => 1520.4,
                'duration' => 300.0,
                'geometry' => [
                    'coordinates' => [[-66.9, 10.5], [-66.9, 10.505], [-66.9, 10.51]],
                ],
            ]],
        ]),
    ]);

    $this->actingAs(User::factory()->create());

    $response = $this->getJson(route('nearby.route', [
        'from_lat' => 10.5,
        'from_lng' => -66.9,
        'to_lat' => 10.51,
        'to_lng' => -66.9,
        'profile' => 'driving',
    ]))->assertOk();

    expect($response->json('route.provider'))->toBe('osrm')
        ->and($response->json('route.distance'))->toBe(1520)
        // GeoJSON llega como [lon, lat] y debe salir como [lat, lng].
        ->and($response->json('route.geometry.0'))->toBe([10.5, -66.9]);
});

test('la ruta a pie usa Valhalla', function () {
    Http::fake([
        '*valhalla*' => Http::response([
            'trip' => [
                'legs' => [['shape' => '_yz_S~`gr~BowH?owH?']],
                'summary' => ['length' => 1.2, 'time' => 900],
            ],
        ]),
    ]);

    $this->actingAs(User::factory()->create());

    $response = $this->getJson(route('nearby.route', [
        'from_lat' => 10.5,
        'from_lng' => -66.9,
        'to_lat' => 10.51,
        'to_lng' => -66.9,
        'profile' => 'walking',
    ]))->assertOk();

    expect($response->json('route.provider'))->toBe('valhalla')
        ->and($response->json('route.distance'))->toBe(1200)
        ->and($response->json('route.geometry.0'))->toBe([10.5, -66.9]);
});

test('si OSRM falla la ruta se resuelve con el motor de respaldo', function () {
    Http::fake([
        '*project-osrm*' => Http::response('', 500),
        '*valhalla*' => Http::response([
            'trip' => [
                'legs' => [['shape' => '_yz_S~`gr~BowH?owH?']],
                'summary' => ['length' => 2.5, 'time' => 420],
            ],
        ]),
    ]);

    $this->actingAs(User::factory()->create());

    $this->getJson(route('nearby.route', [
        'from_lat' => 10.5,
        'from_lng' => -66.9,
        'to_lat' => 10.51,
        'to_lng' => -66.9,
        'profile' => 'driving',
    ]))->assertOk()->assertJsonPath('route.provider', 'valhalla');
});

test('la ruta valida el modo de traslado', function () {
    $this->actingAs(User::factory()->create());

    $this->getJson(route('nearby.route', [
        'from_lat' => 10.5,
        'from_lng' => -66.9,
        'to_lat' => 10.51,
        'to_lng' => -66.9,
        'profile' => 'teletransporte',
    ]))->assertStatus(422);
});

test('la dirección del paciente se resume a calle, sector y ciudad', function () {
    Http::fake([
        '*nominatim*' => Http::response([
            'display_name' => 'Todo el nombre largo de Nominatim',
            'address' => [
                'road' => 'Av. Francisco de Miranda',
                'suburb' => 'Los Palos Grandes',
                'city' => 'Caracas',
                'country' => 'Venezuela',
            ],
        ]),
    ]);

    $this->actingAs(User::factory()->create());

    $this->getJson(route('nearby.address', ['lat' => 10.5, 'lng' => -66.9]))
        ->assertOk()
        ->assertJsonPath('address', 'Av. Francisco de Miranda, Los Palos Grandes, Caracas');
});

test('la pantalla sigue viva aunque el geocodificador falle', function () {
    Http::fake(['*nominatim*' => Http::response('', 500)]);

    $this->actingAs(User::factory()->create());

    $this->getJson(route('nearby.address', ['lat' => 10.5, 'lng' => -66.9]))
        ->assertOk()
        ->assertJsonPath('address', null);
});
