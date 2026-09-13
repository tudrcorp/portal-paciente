<?php

namespace App\Services\Geo\Data;

use InvalidArgumentException;

/**
 * Par latitud/longitud validado. Se usa como moneda común entre controladores
 * y proveedores para que ningún driver reciba coordenadas basura.
 */
final class Coordinates
{
    public function __construct(
        public readonly float $lat,
        public readonly float $lng,
    ) {
        if ($lat < -90.0 || $lat > 90.0) {
            throw new InvalidArgumentException("Latitud fuera de rango: {$lat}");
        }

        if ($lng < -180.0 || $lng > 180.0) {
            throw new InvalidArgumentException("Longitud fuera de rango: {$lng}");
        }
    }

    public static function make(float|string $lat, float|string $lng): self
    {
        return new self((float) $lat, (float) $lng);
    }

    /**
     * Coordenada redondeada, para construir claves de caché estables: sin esto
     * cada metro que se mueve el GPS generaría una entrada nueva.
     */
    public function rounded(int $decimals = 3): string
    {
        return number_format($this->lat, $decimals, '.', '').','.number_format($this->lng, $decimals, '.', '');
    }

    /** Distancia en metros por la fórmula de Haversine. */
    public function distanceTo(self $other): float
    {
        $earthRadius = 6371000.0;

        $lat1 = deg2rad($this->lat);
        $lat2 = deg2rad($other->lat);
        $deltaLat = deg2rad($other->lat - $this->lat);
        $deltaLng = deg2rad($other->lng - $this->lng);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1) * cos($lat2) * sin($deltaLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** @return array{lat: float, lng: float} */
    public function toArray(): array
    {
        return ['lat' => $this->lat, 'lng' => $this->lng];
    }
}
