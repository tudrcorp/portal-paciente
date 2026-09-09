<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Interruptor de contingencia: ¿el portal lee de MySQL o del API Node?
 */
final class PortalDataSource
{
    public const DATABASE = 'database';

    public const API = 'api';

    public static function driver(): string
    {
        $driver = strtolower(trim((string) config('portal.data_source', self::DATABASE)));

        return in_array($driver, [self::DATABASE, self::API], true)
            ? $driver
            : self::DATABASE;
    }

    public static function usesApi(): bool
    {
        return self::driver() === self::API;
    }

    public static function usesDatabase(): bool
    {
        return self::driver() === self::DATABASE;
    }
}
