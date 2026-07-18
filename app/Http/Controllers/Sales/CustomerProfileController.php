<?php

namespace App\Http\Controllers\Sales;

use App\Data\Customers\CustomerProfileFilterData;
use App\Http\Controllers\Controller;
use App\Services\Customers\CustomerProfileOptionsService;
use App\Services\Customers\CustomerProfileService;
use App\Support\RoleScopedRoutes;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerProfileController extends Controller
{
    public function __invoke(
        Request $request,
        CustomerProfileService $service,
        CustomerProfileOptionsService $options,
    ): Response {
        $filter = CustomerProfileFilterData::fromRequest($request, $request->user());
        $path = '/'.$request->path();
        $activeMenuCode = match ($path) {
            '/admin/marketing/customers' => '2.3',
            '/admin/customer-management' => '3.1',
            '/admin/sales/customers' => '4.2',
            default => null,
        };

        return Inertia::render('Sales/CustomerProfile', [
            'filters' => $filter->toArray(),
            'filterOptions' => $options->build($request->user()),
            'report' => $service->paginate($filter),
            'routeUrl' => $path,
            'saleWorkspaceUrl' => RoleScopedRoutes::saleWorkspace($request->user()),
            'activeMenuCode' => $activeMenuCode,
            'pageTitle' => 'Hồ sơ khách hàng',
        ]);
    }
}
