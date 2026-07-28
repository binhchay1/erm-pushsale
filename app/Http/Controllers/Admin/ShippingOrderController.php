<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Concerns\InteractsWithReportFilters;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Inventory\InventoryDeductionService;
use App\Services\Shipping\CreateShipmentService;
use App\Services\Shipping\ShippingOrderService;
use App\Services\Settings\FeatureSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ShippingOrderController extends Controller
{
    use InteractsWithReportFilters;

    private function assertShipmentPermission(Request $request, string $action): ?JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        $features = app(FeatureSettingsService::class);
        $role = $user->role instanceof UserRole ? $user->role : UserRole::tryFrom((string) $user->role);

        $key = match ([$role, $action]) {
            [UserRole::Warehouse, 'create'] => 'SettingKhoDangDon',
            [UserRole::Warehouse, 'cancel'] => 'SettingKhoHuyDangDon',
            [UserRole::Accounting, 'create'] => 'SettingKeToanDangDon',
            [UserRole::Accounting, 'cancel'] => 'SettingKeToanHuyDangDon',
            default => null,
        };

        if ($key && ! $features->bool($key, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Chức năng này đang bị khóa trong Cấu hình chức năng.',
            ], 422);
        }

        return null;
    }


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
        if (! $order->closed_at) {
            return response()->json([
                'success' => false,
                'message' => __('messages.shipping_actions.order_not_closed'),
            ], 422);
        }

        if ($blocked = $this->assertShipmentPermission($request, 'create')) {
            return $blocked;
        }

        if (! $order->inventory_deducted_at && ! app(InventoryDeductionService::class)->hasSufficientStock($order)) {
            return response()->json([
                'success' => false,
                'message' => __('messages.shipping_actions.out_of_stock'),
            ], 422);
        }

        try {
            $provider = $request->string('provider')->toString() ?: null;
            $service->createForOrder($order->fresh(['items', 'warehouse']), $provider);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage() ?: 'Không tạo được vận đơn.',
            ], 422);
        }

        return response()->json(array_merge(
            ['success' => true, 'message' => __('messages.shipping_actions.waybill_created')],
            app(ShippingOrderService::class)->detail($order->fresh()),
        ));
    }

    public function syncStatus(Request $request, Order $order, CreateShipmentService $service, ShippingOrderService $presenter): JsonResponse
    {
        $provider = $request->string('provider')->toString() ?: null;
        $service->sync($order, $provider);

        return response()->json(array_merge(
            ['success' => true, 'message' => __('messages.shipping_actions.status_synced')],
            $presenter->detail($order->fresh()),
        ));
    }

    public function calculateFee(Request $request, Order $order, CreateShipmentService $service): JsonResponse
    {
        $provider = $request->string('provider')->toString() ?: null;

        return response()->json($service->calculateFee($order->fresh(['items', 'warehouse']), $provider));
    }

    public function cancelShipment(Request $request, Order $order, CreateShipmentService $service, ShippingOrderService $presenter): JsonResponse
    {
        if ($blocked = $this->assertShipmentPermission($request, 'cancel')) {
            return $blocked;
        }

        $provider = $request->string('provider')->toString() ?: null;
        $service->cancel($order, $provider);

        return response()->json(array_merge(
            ['success' => true, 'message' => __('messages.shipping_actions.waybill_cancelled')],
            $presenter->detail($order->fresh()),
        ));
    }

    public function printLabel(Request $request, Order $order, CreateShipmentService $service): Response|JsonResponse
    {
        $provider = $request->string('provider')->toString() ?: null;
        $result = $service->printLabel($order, $provider);

        if (! ($result['success'] ?? false) || empty($result['binary'])) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? __('messages.shipping_actions.label_failed'),
                'data' => $result['data'] ?? null,
            ], 422);
        }

        return response($result['binary'], 200, [
            'Content-Type' => $result['content_type'] ?? 'application/pdf',
            'Content-Disposition' => 'inline; filename="label-'.$order->order_code.'.pdf"',
        ]);
    }
}
