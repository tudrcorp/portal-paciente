<?php

namespace App\Services\Geo\Contracts;

use App\Services\Geo\Data\Coordinates;

interface ReverseGeocoder
{
    /**
     * Dirección legible de un punto, o null si el proveedor no la conoce.
     */
    public function reverse(Coordinates $coordinates): ?string;
}
