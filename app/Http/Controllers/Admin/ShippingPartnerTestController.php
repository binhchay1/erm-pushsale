<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Connections\ApiTestDisplayPresenter;
use App\Services\Shipping\CreateShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ShippingPartnerTestController extends Controller
{
    public function __invoke(
        Request $request,
        CreateShipmentService $service,
        ApiTestDisplayPresenter $presenter,
        string $provider,
        string $action,
    ): JsonResponse {
        abort_unless(array_key_exists($provider, config('shipping_partners.providers', [])), 404);

        try {
            $result = $service->runTest($provider, $action);
            $result['display'] = $presenter->present($result, $action);

            if (($result['success'] ?? true) === false && empty($result['message'])) {
                $result['message'] = __('api_test.connection_failed');
            }

            return response()->json($result, ($result['success'] ?? true) ? 200 : 422);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'display' => [
                    'success' => false,
                    'message' => $e->getMessage(),
                    'lines' => [],
                    'items' => [],
                    'options' => [],
                ],
            ], 422);
        }
    }
}
