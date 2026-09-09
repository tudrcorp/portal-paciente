<?php

use App\Support\PortalDataSource;

test('por defecto el origen de datos es database', function () {
    config(['portal.data_source' => 'database']);

    expect(PortalDataSource::driver())->toBe('database')
        ->and(PortalDataSource::usesDatabase())->toBeTrue()
        ->and(PortalDataSource::usesApi())->toBeFalse();
});

test('acepta el origen api', function () {
    config(['portal.data_source' => 'api']);

    expect(PortalDataSource::driver())->toBe('api')
        ->and(PortalDataSource::usesApi())->toBeTrue()
        ->and(PortalDataSource::usesDatabase())->toBeFalse();
});

test('valores inválidos caen a database', function () {
    config(['portal.data_source' => 'redis']);

    expect(PortalDataSource::driver())->toBe('database');
});
