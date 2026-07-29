<?php

namespace App\Http\Controllers\Admin\Sales;

use App\Http\Controllers\Admin\Pushsale\BasePushsalePageController;
use App\Services\Reports\SalesLeader\SalesDataReportService;
use App\Services\Reports\SalesLeader\SalesOptimizationReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SalesOptimizationReportController extends BasePushsalePageController
{
    protected string $pageCode = '4.6.5';

    public function saveAlerts(Request $request, SalesOptimizationReportService $service): RedirectResponse
    {
        $this->authorizePage($request);
        $validated = $request->validate([
            'low_ratio' => ['required', 'numeric', 'min:0', 'max:1000'],
            'high_ratio' => ['required', 'numeric', 'min:0', 'max:1000'],
        ]);

        $companyId = (int) ($request->user()?->company_id ?? 0);
        abort_unless($companyId > 0, 403);
        $service->saveThresholds($companyId, (float) $validated['low_ratio'], (float) $validated['high_ratio']);

        return back()->with('success', 'Đã lưu mức cảnh báo.');
    }

    public function saveTargets(Request $request, SalesOptimizationReportService $service): RedirectResponse
    {
        $this->authorizePage($request);
        $validated = $request->validate([
            'targets' => ['required', 'array', 'min:1'],
            'targets.*.sale_user_id' => ['nullable', 'integer'],
            'targets.*.metric_key' => ['required', 'string', 'max:80'],
            'targets.*.target_value' => ['required', 'numeric'],
        ]);

        $companyId = (int) ($request->user()?->company_id ?? 0);
        abort_unless($companyId > 0, 403);
        $service->saveTargets($companyId, $validated['targets']);

        return back()->with('success', 'Đã lưu mục tiêu sale.');
    }

    public function updateReceiveData(Request $request, SalesDataReportService $service): RedirectResponse
    {
        $this->authorizePage($request);
        $validated = $request->validate([
            'sale_ids' => ['required', 'array', 'min:1'],
            'sale_ids.*' => ['integer'],
            'receive_data' => ['required', 'boolean'],
        ]);

        $updated = $service->updateReceiveData(
            $request->user(),
            $validated['sale_ids'],
            (bool) $validated['receive_data'],
        );

        return back()->with('success', "Đã cập nhật nhận dữ liệu cho {$updated} sale.");
    }
}
