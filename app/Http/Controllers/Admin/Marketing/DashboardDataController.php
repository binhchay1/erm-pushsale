<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Data\MarketingDashboardFilterData;
use App\Http\Controllers\Controller;
use App\Models\MarketingSource;
use App\Services\Reports\PushsaleMarketingDashboardService;
use App\Services\Reporting\ReportSnapshotStore;
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

        $snapshot = app(ReportSnapshotStore::class)->rememberPayload(
            'pushsale-marketing-chart',
            $request->user(),
            array_merge($filter->toInertia(), $validated),
            $filter->dateFrom,
            $filter->dateTo,
            $filter->dateType->value,
            fn () => $service->chartData(
                $filter,
                $source,
                $validated['utm_source'] ?? null,
                $validated['utm_campaign'] ?? null,
            ),
            $request->boolean('refresh'),
        );

        return response()->json($snapshot['data'])
            ->header('X-Report-Cache', $snapshot['storage'])
            ->header('X-Report-Cache-Hit', $snapshot['fromCache'] ? '1' : '0');
    }

    public function packets(
        Request $request,
        PushsaleMarketingDashboardService $service,
    ): JsonResponse {
        $validated = $request->validate([
            'source_id' => ['required', 'integer', 'exists:marketing_sources,id'],
            'utm_source' => ['nullable', 'string', 'max:191'],
            'utm_campaign' => ['nullable', 'string', 'max:191'],
            'packet_page' => ['nullable', 'integer', 'min:1'],
            'packet_per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);
        $source = MarketingSource::query()->findOrFail((int) $validated['source_id']);
        $filter = MarketingDashboardFilterData::fromRequest($request, $request->user());

        return response()->json($service->packetRows(
            $filter,
            $source,
            array_key_exists('utm_source', $validated) ? $validated['utm_source'] : null,
            array_key_exists('utm_campaign', $validated) ? $validated['utm_campaign'] : null,
            (int) ($validated['packet_page'] ?? 1),
            (int) ($validated['packet_per_page'] ?? 20),
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

        $snapshot = app(ReportSnapshotStore::class)->rememberPayload(
            'pushsale-marketing-daily-metrics',
            $request->user(),
            $validated,
            $from,
            $to,
            'marketing_metric_date',
            fn () => $service->dailyMetricRows(
                $source,
                $from,
                $to,
                $validated['utm_source'] ?? null,
                $validated['utm_campaign'] ?? null,
            ),
            $request->boolean('refresh'),
        );

        return response()->json($snapshot['data'])
            ->header('X-Report-Cache', $snapshot['storage'])
            ->header('X-Report-Cache-Hit', $snapshot['fromCache'] ? '1' : '0');
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
        $rows = app(ReportSnapshotStore::class)->rememberPayload(
            'pushsale-marketing-export',
            $request->user(),
            $filter->toInertia(),
            $filter->dateFrom,
            $filter->dateTo,
            $filter->dateType->value,
            fn () => $service->exportRows($filter),
            $request->boolean('refresh'),
        )['data'];
        $filename = 'marketing-dashboard-'.$filter->dateFrom->format('Ymd').'-'.$filter->dateTo->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Cấp', 'Tên nguồn dữ liệu', 'Sản phẩm', 'Kênh quảng cáo', 'UTM Source', 'UTM Campaign',
                'Ngân sách', 'Số tương tác', 'Gói tin chính', 'Gói tin upsale', 'Số contact', 'Tỷ lệ contact', 'Giá contact', 'Chốt đơn',
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
                    $row['baseContacts'] ?? 0,
                    $row['upsaleContacts'] ?? 0,
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
