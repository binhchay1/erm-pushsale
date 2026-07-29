<?php

namespace App\Http\Controllers\Admin\Customers;

use App\Enums\PermissionArea;
use App\Enums\PermissionLevel;
use App\Http\Controllers\Controller;
use App\Services\Customers\CustomerMultidimensionalReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class MultidimensionalReportController extends Controller
{
    public function index(Request $request, CustomerMultidimensionalReportService $reports): Response
    {
        abort_unless($request->user()?->allows(PermissionArea::Customers, PermissionLevel::View), 403);

        $result = $reports->build($request, $request->user());

        return Inertia::render('Admin/Customers/MultidimensionalReport', [
            'pageTitle' => 'Thống kê khách hàng đa chiều',
            'activeMenuCode' => '3.3.1',
            'routeUrl' => '/admin/customers/reports/multidimensional',
            'filters' => $result['filters'],
            'filterOptions' => $result['filterOptions'],
            'rows' => $result['rows'],
            'pagination' => $result['meta'],
        ]);
    }
}
