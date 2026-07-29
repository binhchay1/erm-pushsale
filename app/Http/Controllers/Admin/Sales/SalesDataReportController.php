<?php

namespace App\Http\Controllers\Admin\Sales;

use App\Http\Controllers\Admin\Pushsale\BasePushsalePageController;
use App\Services\Reports\SalesLeader\SalesDataReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SalesDataReportController extends BasePushsalePageController
{
    protected string $pageCode = '4.6.4';

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
