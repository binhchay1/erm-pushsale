<?php

namespace App\Http\Controllers;

use App\Services\OrgStructureService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sơ đồ tổ chức — truy cập: GET /org-chart (đăng nhập bất kỳ role).
 *
 * Test RBAC (sau khi chạy php artisan db:seed):
 * - Admin: admin@saleops.local → thấy toàn bộ cây từ gốc công ty.
 * - Trưởng bộ phận (org_level = head): thấy toàn ngành của mình (mọi team cùng loại).
 * - Leader / nhân viên: chỉ thấy team của mình (gồm leader team).
 */
class OrgChartController extends Controller
{
    public function __construct(
        private readonly OrgStructureService $orgStructure,
    ) {}

    public function index(Request $request): Response
    {
        $viewer = $request->user();
        $chart = $this->orgStructure->chartForViewer($viewer);

        return Inertia::render('OrgChart/Index', [
            'chart' => $chart,
        ]);
    }
}
