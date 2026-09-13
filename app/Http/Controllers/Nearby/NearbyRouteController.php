<?php

namespace App\Http\Controllers\Nearby;

use App\Http\Controllers\Controller;
use App\Http\Requests\NearbyRouteRequest;
use App\Services\Geo\GeoException;
use App\Services\Geo\NearbyLocator;
use Illuminate\Http\JsonResponse;

class NearbyRouteController extends Controller
{
    public function __invoke(NearbyRouteRequest $request, NearbyLocator $locator): JsonResponse
    {
        try {
            $route = $locator->route(
                $request->origin(),
                $request->destination(),
                $request->profile(),
            );
        } catch (GeoException $e) {
            return response()->json([
                'message' => 'No pudimos trazar la ruta hasta ese destino. Intenta de nuevo en un momento.',
                'detail' => $e->getMessage(),
            ], 503);
        }

        return response()->json(['route' => $route->toArray()]);
    }
}
