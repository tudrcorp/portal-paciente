<?php

namespace App\Services\Geo\Support;

/**
 * Decodificador del formato «encoded polyline» de Google, que también usan
 * Valhalla (precisión 6) y la Routes API (precisión 5).
 */
final class Polyline
{
    /**
     * @return array<int, array{0: float, 1: float}> Puntos [lat, lng]
     */
    public static function decode(string $encoded, int $precision = 5): array
    {
        if ($encoded === '') {
            return [];
        }

        $factor = 10 ** $precision;
        $index = 0;
        $lat = 0;
        $lng = 0;
        $points = [];
        $length = strlen($encoded);

        while ($index < $length) {
            foreach (['lat', 'lng'] as $axis) {
                $shift = 0;
                $result = 0;

                do {
                    if ($index >= $length) {
                        return $points;
                    }

                    $byte = ord($encoded[$index++]) - 63;
                    $result |= ($byte & 0x1F) << $shift;
                    $shift += 5;
                } while ($byte >= 0x20);

                $delta = ($result & 1) ? ~($result >> 1) : ($result >> 1);

                if ($axis === 'lat') {
                    $lat += $delta;
                } else {
                    $lng += $delta;
                }
            }

            $points[] = [$lat / $factor, $lng / $factor];
        }

        return $points;
    }
}
