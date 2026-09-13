<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * El valor de la capa de proveedores es poder cambiar de motor sin tocar la
 * interfaz. Estas pruebas fijan ese contrato: con GEO_DRIVER=google la
 * respuesta que recibe el navegador tiene exactamente la misma forma.
 */
beforeEach(function () {
    // La capa de geolocalización cachea por coordenada: sin vaciarla, una
    // prueba heredaría la respuesta de la anterior.
    Cache::flush();

    config()->set('geolocation.driver', 'google');
    config()->set('geolocation.google.key', 'clave-de-prueba');
});

test('con el driver de Google la búsqueda usa Places API y conserva la forma de la respuesta', function () {
    Http::fake([
        '*places.googleapis.com*' => Http::response([
            'places' => [
                [
                    'id' => 'ChIJ123',
                    'displayName' => ['text' => 'Farmacia Central'],
                    'formattedAddress' => 'Av. Libertador, Caracas',
                    'location' => ['latitude' => 10.5010, 'longitude' => -66.9000],
                    'nationalPhoneNumber' => '0212-1234567',
                    'regularOpeningHours' => ['openNow' => true],
                ],
            ],
        ]),
    ]);

    $this->actingAs(User::factory()->create());

    $response = $this->getJson(route('nearby.places', [
        'lat' => 10.5,
        'lng' => -66.9,
        'radius' => 5000,
        'categories' => ['pharmacy'],
    ]))->assertOk();

    expect($response->json('places.0'))->toHaveKeys(['id', 'name', 'category', 'lat', 'lng', 'distance'])
        ->and($response->json('places.0.name'))->toBe('Farmacia Central')
        ->and($response->json('places.0.category'))->toBe('pharmacy')
        ->and($response->json('places.0.opening_hours'))->toBe('Abierto ahora');

    Http::assertSent(fn ($request) => $request->hasHeader('X-Goog-Api-Key', 'clave-de-prueba'));
});

test('con el driver de Google la ruta usa Routes API', function () {
    Http::fake([
        '*routes.googleapis.com*' => Http::response([
            'routes' => [[
                'distanceMeters' => 3400,
                'duration' => '660s',
                'polyline' => ['encodedPolyline' => '_xa_A~kiwKo}@?'],
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

    expect($response->json('route.provider'))->toBe('google')
        ->and($response->json('route.distance'))->toBe(3400)
        ->and($response->json('route.duration'))->toBe(660)
        ->and($response->json('route.geometry.0'))->toBe([10.5, -66.9]);
});

test('sin clave configurada el portal responde con un aviso en lugar de romperse', function () {
    config()->set('geolocation.google.key', '');

    $this->actingAs(User::factory()->create());

    $this->getJson(route('nearby.places', [
        'lat' => 10.5,
        'lng' => -66.9,
        'radius' => 5000,
        'categories' => ['pharmacy'],
    ]))->assertStatus(503);
});
