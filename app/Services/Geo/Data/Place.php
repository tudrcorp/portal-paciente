<?php

namespace App\Services\Geo\Data;

/**
 * Lugar sanitario normalizado. La forma de este objeto es el contrato que ve
 * el frontend: da igual si detrás respondió Overpass o Google Places.
 */
final class Place
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $category,
        public readonly Coordinates $coordinates,
        public readonly ?string $address = null,
        public readonly ?string $phone = null,
        public readonly ?string $openingHours = null,
        public readonly ?string $website = null,
        public readonly ?bool $emergency = null,
        public readonly ?float $distance = null,
    ) {}

    /** Copia con la distancia ya calculada respecto al usuario. */
    public function withDistance(float $meters): self
    {
        return new self(
            $this->id,
            $this->name,
            $this->category,
            $this->coordinates,
            $this->address,
            $this->phone,
            $this->openingHours,
            $this->website,
            $this->emergency,
            $meters,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'lat' => $this->coordinates->lat,
            'lng' => $this->coordinates->lng,
            'address' => $this->address,
            'phone' => $this->phone,
            'opening_hours' => $this->openingHours,
            'website' => $this->website,
            'emergency' => $this->emergency,
            'distance' => $this->distance !== null ? round($this->distance) : null,
        ];
    }
}
