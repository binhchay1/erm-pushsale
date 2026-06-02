<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Admin\ShippingOrderController as AdminShippingOrderController;
use App\Services\Shipping\ShippingOrderService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Kho xem cùng màn đơn vận chuyển — route prefix khác. */
class ShippingOrderController extends AdminShippingOrderController
{
    public function index(Request $request, ShippingOrderService $service): Response
    {
        $filter = $this->reportFilters($request);
        $paginator = $service->paginate($filter);

        return Inertia::render('Admin/Shipping/Orders', $this->reportPageProps($request, [
            'orders' => [
                'data' => collect($paginator->items())->map(fn ($order) => $service->presentRow($order))->values(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'pageTitle' => 'Đơn vận chuyển',
            'routeUrl' => '/warehouse/shipping/orders',
        ]));
    }
}
