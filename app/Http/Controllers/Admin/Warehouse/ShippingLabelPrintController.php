<?php

namespace App\Http\Controllers\Admin\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Warehouse\ShippingLabelPrintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShippingLabelPrintController extends Controller
{
    public function show(Request $request, string $profile, ShippingLabelPrintService $service): Response
    {
        $ids = $this->parseIds($request);
        $payload = $service->buildPage($profile, $ids, $request->user());
        $backUrl = $this->backUrl($request);

        return Inertia::render('Admin/Warehouse/ShippingLabelPrint', array_merge($payload, [
            'backUrl' => $backUrl,
            'shippingApiBase' => $this->shippingApiBase($request),
            'actionApiBase' => $this->actionApiBase($request),
            'activeMenuCode' => $request->routeIs('accounting.*') ? '6.1' : '5.1',
            'printUrl' => $this->printBase($request).'/'.$profile,
        ]));
    }

    public function markPrinted(Request $request, ShippingLabelPrintService $service): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:orders,id'],
        ]);

        return response()->json($service->markPrinted($data['ids'], $request->user()));
    }

    public function carrierLabel(Request $request, Order $order, string $profile, ShippingLabelPrintService $service): JsonResponse
    {
        $service->profile($profile);
        $provider = $request->string('provider')->toString() ?: null;

        return response()->json($service->carrierLabelPayload($order, $provider));
    }

    public function profiles(ShippingLabelPrintService $service): JsonResponse
    {
        return response()->json(['buttons' => $service->fabButtons()]);
    }

    /** @return list<int> */
    private function parseIds(Request $request): array
    {
        $raw = $request->query('ids', $request->input('ids', []));
        if (is_string($raw)) {
            $raw = preg_split('/[,\s]+/', $raw) ?: [];
        }
        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $raw)));
    }

    private function backUrl(Request $request): string
    {
        if ($request->routeIs('warehouse.*')) {
            return '/warehouse/workspace';
        }
        if ($request->routeIs('accounting.*')) {
            return '/accounting/workspace';
        }

        return '/admin/warehouse/operations';
    }

    private function shippingApiBase(Request $request): string
    {
        if ($request->routeIs('warehouse.*')) {
            return '/warehouse/shipping/orders';
        }
        if ($request->routeIs('accounting.*')) {
            return '/accounting/shipping/orders';
        }

        return '/admin/shipping/orders';
    }

    private function actionApiBase(Request $request): string
    {
        if ($request->routeIs('warehouse.*')) {
            return '/warehouse/orders';
        }
        if ($request->routeIs('accounting.*')) {
            return '/accounting/orders';
        }

        return '/admin/warehouse/orders';
    }

    private function printBase(Request $request): string
    {
        if ($request->routeIs('warehouse.*')) {
            return '/warehouse/orders/print';
        }
        if ($request->routeIs('accounting.*')) {
            return '/accounting/orders/print';
        }

        return '/admin/warehouse/orders/print';
    }
}
