<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\InteractsWithReportFilters;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Shipping\CreateShipmentService;
use App\Services\Shipping\ShippingOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ShippingOrderController extends Controller
{
    use InteractsWithReportFilters;

    public function index(Request $request, ShippingOrderService $service): InertiaResponse
    {
        $filter = $this->reportFilters($request);
        $paginator = $service->paginate($filter);

        return Inertia::render('Admin/Shipping/Orders', $this->reportPageProps($request, [
            'orders' => [
                'data' => collect($paginator->items())->map(fn (Order $order) => $service->presentRow($order))->values(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'pageTitle' => 'Đơn vận chuyển',
            'routeUrl' => '/admin/shipping/orders',
        ]));
    }

    public function detail(Order $order, ShippingOrderService $service): JsonResponse
    {
        abort_unless($order->closed_at, 404);

        return response()->json($service->detail($order));
    }

    public function createShipment(Request $request, Order $order, CreateShipmentService $service): JsonResponse
    {
        abort_unless($order->closed_at, 422, 'Đơn chưa chốt.');

        $provider = $request->string('provider')->toString() ?: null;
        $service->createForOrder($order->fresh(['items', 'warehouse']), $provider);

        return response()->json(array_merge(
            ['success' => true, 'message' => 'Đã tạo vận đơn.'],
            app(ShippingOrderService::class)->detail($order->fresh()),
        ));
    }

    public function syncStatus(Request $request, Order $order, CreateShipmentService $service, ShippingOrderService $presenter): JsonResponse
    {
        $provider = $request->string('provider')->toString() ?: null;
        $service->sync($order, $provider);

        return response()->json($presenter->detail($order->fresh()));
    }

    public function calculateFee(Request $request, Order $order, CreateShipmentService $service): JsonResponse
    {
        $provider = $request->string('provider')->toString() ?: null;

        return response()->json($service->calculateFee($order->fresh(['items', 'warehouse']), $provider));
    }

    public function cancelShipment(Request $request, Order $order, CreateShipmentService $service, ShippingOrderService $presenter): JsonResponse
    {
        $provider = $request->string('provider')->toString() ?: null;
        $service->cancel($order, $provider);

        return response()->json($presenter->detail($order->fresh()));
    }

    public function printLabel(Request $request, Order $order, CreateShipmentService $service): Response|JsonResponse
    {
        $provider = $request->string('provider')->toString() ?: null;
        $result = $service->printLabel($order, $provider);

        if (! ($result['success'] ?? false) || empty($result['binary'])) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Không in được nhãn.',
                'data' => $result['data'] ?? null,
            ], 422);
        }

        return response($result['binary'], 200, [
            'Content-Type' => $result['content_type'] ?? 'application/pdf',
            'Content-Disposition' => 'inline; filename="label-'.$order->order_code.'.pdf"',
        ]);
    }
}
