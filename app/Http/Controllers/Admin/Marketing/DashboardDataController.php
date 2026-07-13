<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Data\MarketingDashboardFilterData;
use App\Http\Controllers\Controller;
use App\Models\MarketingSource;
use App\Services\Reports\PushsaleMarketingDashboardService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardDataController extends Controller
{
    public function chart(
        Request $request,
        PushsaleMarketingDashboardService $service,
    ): JsonResponse {
        $validated = $request->validate([
            'source_id' => ['required', 'integer', 'exists:marketing_sources,id'],
            'utm_source' => ['nullable', 'string', 'max:191'],
            'utm_campaign' => ['nullable', 'string', 'max:191'],
        ]);
        $source = MarketingSource::query()->findOrFail((int) $validated['source_id']);
        $filter = MarketingDashboardFilterData::fromRequest($request, $request->user());

        return response()->json($service->chartData(
            $filter,
            $source,
            $validated['utm_source'] ?? null,
            $validated['utm_campaign'] ?? null,
        ));
    }

    public function dailyMetrics(
        Request $request,
        PushsaleMarketingDashboardService $service,
    ): JsonResponse {
        $validated = $request->validate([
            'source_id' => ['required', 'integer', 'exists:marketing_sources,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'utm_source' => ['nullable', 'string', 'max:191'],
            'utm_campaign' => ['nullable', 'string', 'max:191'],
        ]);
        $source = MarketingSource::query()->with('product:id,name')->findOrFail((int) $validated['source_id']);
        $from = Carbon::parse($validated['date_from'] ?? now()->toDateString())->startOfDay();
        $to = Carbon::parse($validated['date_to'] ?? $from->toDateString())->endOfDay();

        return response()->json($service->dailyMetricRows(
            $source,
            $from,
            $to,
            $validated['utm_source'] ?? null,
            $validated['utm_campaign'] ?? null,
        ));
    }

    public function saveDailyMetrics(
        Request $request,
        PushsaleMarketingDashboardService $service,
    ): JsonResponse {
        $validated = $request->validate([
            'source_id' => ['required', 'integer', 'exists:marketing_sources,id'],
            'rows' => ['required', 'array', 'min:1', 'max:32'],
            'rows.*.metric_date' => ['required', 'date'],
            'rows.*.utm_source' => ['nullable', 'string', 'max:191'],
            'rows.*.utm_campaign' => ['nullable', 'string', 'max:191'],
            'rows.*.budget' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'rows.*.clicks' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ]);
        $source = MarketingSource::query()->findOrFail((int) $validated['source_id']);
        $saved = $service->saveDailyMetrics($request->user(), $source, $validated['rows']);

        return response()->json([
            'message' => "Đã lưu {$saved} dòng dữ liệu marketing.",
            'saved' => $saved,
        ]);
    }

    public function export(
        Request $request,
        PushsaleMarketingDashboardService $service,
    ): StreamedResponse {
        $filter = MarketingDashboardFilterData::fromRequest($request, $request->user());
        $rows = $service->exportRows($filter);
        $filename = 'marketing-dashboard-'.$filter->dateFrom->format('Ymd').'-'.$filter->dateTo->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Cấp', 'Tên nguồn dữ liệu', 'Sản phẩm', 'Kênh quảng cáo', 'UTM Source', 'UTM Campaign',
                'Ngân sách', 'Số tương tác', 'Số contact', 'Tỷ lệ contact', 'Giá contact', 'Chốt đơn',
                'Tỷ lệ chốt', 'Số sản phẩm', 'Sản phẩm/đơn', 'Doanh số', 'Doanh số sau CK',
                'NS/Doanh số', 'NS/Doanh số sau CK',
            ]);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['level'] ?? 1,
                    $row['sourceName'] ?? '',
                    $row['productName'] ?? '',
                    $row['adChannel'] ?? '',
                    $row['utmSource'] ?? '',
                    $row['utmCampaign'] ?? '',
                    $row['budget'] ?? 0,
                    $row['interactions'] ?? 0,
                    $row['contacts'] ?? 0,
                    $row['contactRate'] ?? '',
                    $row['costPerContact'] ?? 0,
                    $row['closedOrders'] ?? 0,
                    $row['closingRate'] ?? 0,
                    $row['productQuantity'] ?? 0,
                    $row['avgProductPerOrder'] ?? 0,
                    $row['totalRevenue'] ?? 0,
                    $row['revenueAfterDiscount'] ?? 0,
                    $row['budgetRevenueRatio'] ?? 0,
                    $row['budgetNetRevenueRatio'] ?? 0,
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
