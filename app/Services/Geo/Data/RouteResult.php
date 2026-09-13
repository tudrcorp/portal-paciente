<?php

namespace App\Services\Geo\Data;

/**
 * Ruta normalizada: geometría en [lat, lng] lista para Leaflet, distancia en
 * metros y duración en segundos.
 */
final class RouteResult
{
    /**
     * @param  array<int, array{0: float, 1: float}>  $geometry
     */
    public function __construct(
        public readonly array $geometry,
        public readonly float $distance,
        public readonly float $duration,
        public readonly string $profile,
        public readonly string $provider,
    ) {}

    public function toArray(): array
    {
        return [
            'geometry' => $this->geometry,
            'distance' => round($this->distance),
            'duration' => round($this->duration),
            'profile' => $this->profile,
            'provider' => $this->provider,
        ];
    }
}
