<?php

namespace App\Http\Controllers\Nearby;

use App\Http\Controllers\Controller;
use App\Http\Requests\NearbyPlacesRequest;
use App\Services\Geo\GeoException;
use App\Services\Geo\NearbyLocator;
use Illuminate\Http\JsonResponse;

class NearbyPlacesController extends Controller
{
    public function __invoke(NearbyPlacesRequest $request, NearbyLocator $locator): JsonResponse
    {
        try {
            $places = $locator->places(
                $request->center(),
                $request->categories(),
                $request->radius(),
            );
        } catch (GeoException $e) {
            return response()->json([
                'message' => 'No pudimos consultar los centros de salud cercanos. Intenta de nuevo en un momento.',
                'detail' => $e->getMessage(),
            ], 503);
        }

        return response()->json([
            'places' => $places,
            'count' => count($places),
        ]);
    }
}
