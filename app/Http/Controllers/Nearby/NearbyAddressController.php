<?php

namespace App\Http\Controllers\Nearby;

use App\Http\Controllers\Controller;
use App\Http\Requests\NearbyAddressRequest;
use App\Services\Geo\NearbyLocator;
use Illuminate\Http\JsonResponse;

class NearbyAddressController extends Controller
{
    public function __invoke(NearbyAddressRequest $request, NearbyLocator $locator): JsonResponse
    {
        return response()->json([
            'address' => $locator->address($request->coordinates()),
        ]);
    }
}
