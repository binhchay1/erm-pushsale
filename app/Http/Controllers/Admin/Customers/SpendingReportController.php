<?php

namespace App\Http\Controllers\Admin\Customers;

use App\Enums\PermissionArea;
use App\Enums\PermissionLevel;
use App\Http\Controllers\Controller;
use App\Services\Customers\CustomerSpendingReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class SpendingReportController extends Controller
{
    public function index(Request $request, CustomerSpendingReportService $reports): Response
    {
        abort_unless($request->user()?->allows(PermissionArea::Customers, PermissionLevel::View), 403);

        $result = $reports->build($request, $request->user());

        return Inertia::render('Admin/Customers/SpendingReport', [
            'pageTitle' => 'Thống kê khách hàng chi trả',
            'activeMenuCode' => '3.3.2',
            'routeUrl' => '/admin/customers/reports/spending',
            'filters' => $result['filters'],
            'filterOptions' => $result['filterOptions'],
            'rows' => $result['rows'],
            'pagination' => $result['meta'],
        ]);
    }
}
