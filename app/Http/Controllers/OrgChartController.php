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
 * - Quản lý: user có org_level head/supervisor hoặc is_team_leader → thấy nhánh từ Giám đốc bộ phận trở xuống.
 * - Nhân viên: org_level staff (mặc định) → thấy quản lý trực tiếp, đồng cấp và cấp dưới của quản lý đó.
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
