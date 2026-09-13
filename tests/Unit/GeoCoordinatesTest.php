<?php

use App\Services\Geo\Data\Coordinates;
use App\Services\Geo\Support\Polyline;

test('la distancia entre dos puntos usa haversine', function () {
    $caracas = new Coordinates(10.5000, -66.9000);
    $unKilometroAlNorte = new Coordinates(10.5090, -66.9000);

    expect($caracas->distanceTo($unKilometroAlNorte))->toBeGreaterThan(980)
        ->and($caracas->distanceTo($unKilometroAlNorte))->toBeLessThan(1020);
});

test('la clave de caché redondea las coordenadas', function () {
    expect((new Coordinates(10.500449, -66.900449))->rounded(3))->toBe('10.500,-66.900');
});

test('las coordenadas fuera de rango se rechazan', function () {
    expect(fn () => new Coordinates(91.0, 0.0))->toThrow(InvalidArgumentException::class);
    expect(fn () => new Coordinates(0.0, 181.0))->toThrow(InvalidArgumentException::class);
});

test('la polilínea se decodifica con la precisión indicada', function () {
    expect(Polyline::decode('_yz_S~`gr~BowH?owH?', 6))->toBe([
        [10.5, -66.9],
        [10.505, -66.9],
        [10.51, -66.9],
    ]);

    expect(Polyline::decode('_xa_A~kiwKo}@?', 5))->toBe([
        [10.5, -66.9],
        [10.51, -66.9],
    ]);
});

test('una polilínea vacía no rompe el trazado', function () {
    expect(Polyline::decode(''))->toBe([]);
});
