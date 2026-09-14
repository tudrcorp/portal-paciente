<?php

/*
|--------------------------------------------------------------------------
| Geolocalización — proveedores de lugares y rutas
|--------------------------------------------------------------------------
|
| El portal nunca habla directamente con el proveedor desde el navegador:
| siempre pasa por los endpoints propios (/nearby/*). Eso permite cachear,
| ocultar credenciales y — sobre todo — cambiar de proveedor sin tocar la UI.
|
| Driver «osm»    → OpenStreetMap (Overpass + OSRM/Valhalla + Nominatim).
|                   Gratis y sin API key. Pensado para desarrollo y pruebas.
| Driver «google» → Places API + Directions API + Geocoding API.
|                   Requiere GOOGLE_MAPS_KEY. Pensado para producción.
|
*/

return [

    'driver' => env('GEO_DRIVER', 'osm'),

    // Identifica al portal ante las APIs públicas de OSM (su política de uso
    // exige un User-Agent real y de contacto).
    'user_agent' => env(
        'GEO_USER_AGENT',
        'PortalPacienteTDG/1.0 (+'.env('APP_URL', 'https://portal-paciente.test').')'
    ),

    'http' => [
        'timeout' => (int) env('GEO_HTTP_TIMEOUT', 12),
        'connect_timeout' => (int) env('GEO_HTTP_CONNECT_TIMEOUT', 5),
    ],

    // Radios ofrecidos en la UI (metros). El primero marcado como default.
    'radii' => [5000, 10000, 15000],
    'default_radius' => 5000,
    'max_radius' => 25000,

    // Máximo de lugares devueltos por consulta; evita listas infinitas y
    // mantiene el mapa fluido en gama baja.
    'max_results' => 60,

    /*
    | Categorías expuestas al paciente. Cada una declara cómo se traduce en
    | cada proveedor, de modo que el contrato público («pharmacy», «hospital»…)
    | sea estable aunque cambie el motor de datos.
    */
    'categories' => [
        'pharmacy' => [
            'label' => 'Farmacias',
            'icon' => 'pharmacy',
            'overpass' => [
                ['amenity' => 'pharmacy'],
                ['healthcare' => 'pharmacy'],
            ],
            'google_types' => ['pharmacy', 'drugstore'],
        ],
        'hospital' => [
            'label' => 'Hospitales',
            'icon' => 'hospital',
            'overpass' => [
                ['amenity' => 'hospital'],
                ['healthcare' => 'hospital'],
            ],
            'google_types' => ['hospital'],
        ],
        'clinic' => [
            'label' => 'Clínicas',
            'icon' => 'clinic',
            'overpass' => [
                ['amenity' => 'clinic'],
                ['healthcare' => 'clinic'],
            ],
            'google_types' => ['doctor', 'medical_clinic'],
        ],
        'ambulatory' => [
            'label' => 'Ambulatorios',
            'icon' => 'ambulatory',
            'overpass' => [
                ['amenity' => 'doctors'],
                ['healthcare' => 'centre'],
                ['healthcare' => 'doctor'],
            ],
            'google_types' => ['doctor'],
        ],
    ],

    'osm' => [
        // Se intentan en orden: si el primero está saturado se usa el siguiente.
        'overpass_endpoints' => array_values(array_filter(explode(',', (string) env(
            'GEO_OVERPASS_ENDPOINTS',
            'https://overpass-api.de/api/interpreter,https://overpass.kumi.systems/api/interpreter'
        )))),
        'osrm_base' => env('GEO_OSRM_BASE', 'https://router.project-osrm.org'),
        'valhalla_base' => env('GEO_VALHALLA_BASE', 'https://valhalla1.openstreetmap.de'),
        'nominatim_base' => env('GEO_NOMINATIM_BASE', 'https://nominatim.openstreetmap.org'),
    ],

    'google' => [
        'key' => env('GOOGLE_MAPS_KEY'),
        'places_base' => env('GEO_GOOGLE_PLACES_BASE', 'https://places.googleapis.com/v1'),
        'routes_base' => env('GEO_GOOGLE_ROUTES_BASE', 'https://routes.googleapis.com'),
        'geocode_base' => env('GEO_GOOGLE_GEOCODE_BASE', 'https://maps.googleapis.com/maps/api/geocode'),
        'language' => env('GEO_GOOGLE_LANGUAGE', 'es'),
        'region' => env('GEO_GOOGLE_REGION', 've'),
    ],

    'cache' => [
        // Los POI sanitarios cambian poco: cachear fuerte reduce muchísimo la
        // presión sobre Overpass (y el costo cuando se pase a Google).
        'places_ttl' => (int) env('GEO_CACHE_PLACES_TTL', 21600),   // 6 h
        'route_ttl' => (int) env('GEO_CACHE_ROUTE_TTL', 3600),      // 1 h
        'geocode_ttl' => (int) env('GEO_CACHE_GEOCODE_TTL', 86400), // 24 h
    ],

    /*
    | Teselas del mapa: servidor oficial de OpenStreetMap, gratuito y sin API
    | key. Solo existe en estilo claro, así que la pantalla del mapa se fija en
    | tema claro aunque el portal esté en oscuro (ver resources/css/nearby.css).
    | Al migrar a Google, este bloque deja de usarse.
    */
    'tiles' => [
        'url' => env('GEO_TILES_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'subdomains' => env('GEO_TILES_SUBDOMAINS', ''),
        'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    ],

    // Encuadre inicial mientras el navegador resuelve el GPS (Caracas).
    'fallback_center' => [
        'lat' => (float) env('GEO_FALLBACK_LAT', 10.4806),
        'lng' => (float) env('GEO_FALLBACK_LNG', -66.9036),
    ],
];
