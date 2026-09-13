<?php

namespace App\Services\Geo\Contracts;

use App\Services\Geo\Data\Coordinates;
use App\Services\Geo\Data\Place;

interface PlacesProvider
{
    /**
     * Lugares sanitarios dentro de un radio, ordenados por cercanía real.
     *
     * @param  array<int, string>  $categories  Claves de config('geolocation.categories')
     * @param  int  $radius  Metros
     * @return array<int, Place>
     */
    public function search(Coordinates $center, array $categories, int $radius, int $limit): array;
}
