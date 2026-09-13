<?php

namespace App\Services\Geo\Contracts;

use App\Services\Geo\Data\Coordinates;
use App\Services\Geo\Data\RouteResult;

interface RoutingProvider
{
    /**
     * Ruta más corta entre dos puntos.
     *
     * @param  string  $profile  «driving» | «walking»
     */
    public function route(Coordinates $from, Coordinates $to, string $profile): RouteResult;
}
