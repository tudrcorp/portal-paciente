<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pantalla «Cerca de mí»: mapa a pantalla completa con el panel de búsqueda.
 *
 * El servidor solo entrega la configuración inicial; la ubicación del paciente
 * nunca se persiste ni viaja en el HTML — la resuelve el navegador y se usa
 * de forma efímera contra los endpoints de búsqueda.
 */
class NearbyController extends Controller
{
    public function __invoke(Request $request): View
    {
        $categories = collect((array) config('geolocation.categories', []))
            ->map(fn (array $category, string $key) => [
                'key' => $key,
                'label' => $category['label'],
                'icon' => $category['icon'] ?? 'clinic',
            ])
            ->values()
            ->all();

        return view('nearby', [
            'geoConfig' => [
                'endpoints' => [
                    'places' => route('nearby.places'),
                    'route' => route('nearby.route'),
                    'address' => route('nearby.address'),
                ],
                'categories' => $categories,
                'radii' => array_values((array) config('geolocation.radii', [5000, 10000, 15000])),
                'tiles' => (array) config('geolocation.tiles'),
                'defaultRadius' => (int) config('geolocation.default_radius', 5000),
                'fallbackCenter' => (array) config('geolocation.fallback_center'),
                'provider' => (string) config('geolocation.driver'),
            ],
        ]);
    }
}
