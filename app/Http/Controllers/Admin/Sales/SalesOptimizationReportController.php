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
            'high_ratio' => ['required', 'numeric', 'min:0', 'max:1000', 'gte:low_ratio'],
        ], [
            'high_ratio.gte' => __('reports.sales_optimization.alerts_high_gte_low'),
        ]);

        $companyId = (int) ($request->user()?->company_id ?? 0);
        abort_unless($companyId > 0, 403);
        $service->saveThresholds($companyId, (float) $validated['low_ratio'], (float) $validated['high_ratio']);

        return back()->with('success', __('reports.sales_optimization.alerts_saved'));
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

        return back()->with('success', __('reports.sales_optimization.targets_saved'));
    }

    public function saveCatalogs(Request $request, SalesOptimizationReportService $service): RedirectResponse
    {
        $this->authorizePage($request);
        $validated = $request->validate([
            'leader_user_id' => ['required', 'integer', 'min:1'],
            'catalogs' => ['required', 'array', 'min:1'],
            'catalogs.*.id' => ['nullable', 'integer'],
            'catalogs.*.name' => ['required', 'string', 'max:120'],
            'catalogs.*.metrics' => ['nullable', 'array'],
            'catalogs.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $companyId = (int) ($request->user()?->company_id ?? 0);
        abort_unless($companyId > 0, 403);
        $service->saveCatalogs($companyId, (int) $validated['leader_user_id'], $validated['catalogs']);

        return back()->with('success', __('reports.sales_optimization.catalogs_saved'));
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

        return back()->with('success', __('reports.sales_optimization.receive_saved', ['count' => $updated]));
    }
}
