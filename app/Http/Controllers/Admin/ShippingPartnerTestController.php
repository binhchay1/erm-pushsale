<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Shipping\CreateShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingPartnerTestController extends Controller
{
    public function __invoke(Request $request, CreateShipmentService $service, string $provider, string $action): JsonResponse
    {
        abort_unless(array_key_exists($provider, config('shipping_partners.providers', [])), 404);

        return response()->json($service->runTest($provider, $action));
    }
}
