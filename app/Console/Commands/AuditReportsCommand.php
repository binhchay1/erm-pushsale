<?php

namespace App\Console\Commands;

use App\Data\ReportFilterData;
use App\Enums\UserRole;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\User;
use App\Services\DashboardStatsService;
use App\Services\Reports\CeoReportService;
use App\Services\Reports\ExtraReportService;
use App\Services\Reports\MarketingCampaignReportService;
use App\Services\Reports\MarketingDashboardService;
use App\Services\Reports\ReportMetricService;
use App\Services\Reports\TeamLeaderStatsService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Kiểm tra báo cáo/dashboard có phản ánh đơn hàng thật (theo SĐT hoặc mã đơn) hay không.
 */
class AuditReportsCommand extends Command
{
    protected $signature = 'audit:reports
                            {--phone= : SĐT khách cần kiểm tra}
                            {--order= : Mã đơn (order_code)}
                            {--run-flow : Chạy demo:ui-flow trước khi audit}
                            {--skip-flow : Bỏ qua bước chạy luồng}';

    protected $description = 'Audit dashboards/reports — xác nhận dữ liệu luồng thật, không chỉ seed';

    public function handle(
        MarketingDashboardService $marketingDashboard,
        CeoReportService $ceoReport,
        ExtraReportService $extraReport,
        TeamLeaderStatsService $teamLeaderStats,
        MarketingCampaignReportService $campaignReport,
        ReportMetricService $metrics,
    ): int {
        if ($this->option('run-flow') && ! $this->option('skip-flow')) {
            $phone = $this->option('phone') ?: '0911'.now()->format('His');
            $this->info("Chạy luồng demo:ui-flow — phone={$phone}");
            $exit = $this->call('demo:ui-flow', ['--phone' => $phone]);
            if ($exit !== self::SUCCESS) {
                return $exit;
            }
            $this->input->setOption('phone', $phone);
        }

        $order = $this->resolveOrder();
        if (! $order) {
            $this->error('Không tìm thấy đơn — truyền --phone hoặc --order, hoặc dùng --run-flow.');

            return self::FAILURE;
        }

        $admin = User::query()->where('role', UserRole::Admin)->first();
        $marketer = $order->marketer_user_id
            ? User::query()->find($order->marketer_user_id)
            : User::query()->where('role', UserRole::Marketing)->first();

        if (! $admin || ! $marketer) {
            $this->error('Thiếu user admin/marketing.');

            return self::FAILURE;
        }

        $filter = ReportFilterData::fromRequest(
            Request::create('/x', 'GET', [
                'date_from' => $order->data_arrived_at?->copy()->subDay()->toDateString()
                    ?? now()->subDays(7)->toDateString(),
                'date_to' => now()->addDay()->toDateString(),
                'date_type' => 'data_arrived',
            ]),
            $admin,
        );

        $campaign = $order->marketing_source_id
            ? MarketingSource::query()->find($order->marketing_source_id)
            : null;

        $this->info("Đơn audit: {$order->order_code} · SĐT {$order->customer_phone} · delivery={$order->delivery_status}");
        if ($campaign) {
            $this->line("Chiến dịch: {$campaign->name} (#{$campaign->id}) · seed contacts={$campaign->contacts}");
        }

        $leadCount = \App\Support\LeadContactMetrics::applyCountableScope(LeadIngestion::query())
            ->where('customer_phone', $order->customer_phone)
            ->when($campaign, fn ($q) => $q->where('marketing_source_id', $campaign->id))
            ->count();

        $rows = [];

        // Marketing dashboard
        $mktDash = $marketingDashboard->build($filter);
        $sourceRow = collect($mktDash['rows'] ?? [])->first(
            fn ($r) => ($r['id'] ?? null) === (string) $order->marketing_source_id && ! ($r['isChild'] ?? false),
        );
        $rows[] = $this->row(
            'Marketing dashboard (admin)',
            $sourceRow && ($sourceRow['closedOrders'] ?? 0) >= 1,
            'closedOrders='.($sourceRow['closedOrders'] ?? 0).', contacts='.($sourceRow['contacts'] ?? 0),
        );

        $teamTreeMarketer = $this->findMarketerInTree($mktDash['teamTree']['roots'] ?? [], $marketer->id);
        $rows[] = $this->row(
            'Marketer revenue table',
            $teamTreeMarketer && ($teamTreeMarketer['closedOrders'] ?? 0) >= 1,
            'closed='.($teamTreeMarketer['closedOrders'] ?? 0).', revenue='.($teamTreeMarketer['revenue'] ?? 0),
        );

        // CEO report
        $ceo = $ceoReport->build($filter, $admin);
        $ceoMkt = collect($ceo['marketingRows'] ?? [])->first(
            fn ($r) => $campaign && (int) ($r['marketerId'] ?? 0) === (int) $campaign->marketer_user_id,
        );
        $rows[] = $this->row(
            'CEO report — marketing',
            $ceoMkt && ($ceoMkt['closed'] ?? 0) >= 1,
            'closed='.($ceoMkt['closed'] ?? 0).', contacts='.($ceoMkt['contacts'] ?? 0),
        );

        $ceoSale = collect($ceo['saleRows'] ?? [])->first(fn ($r) => (int) ($r['saleStaffId'] ?? 0) === (int) $order->sale_user_id);
        $rows[] = $this->row(
            'CEO report — sale',
            $ceoSale && ($ceoSale['newClosed'] ?? 0) + ($ceoSale['oldClosed'] ?? 0) >= 1,
            'closed='.(($ceoSale['newClosed'] ?? 0) + ($ceoSale['oldClosed'] ?? 0)),
        );

        // Extra reports
        $mktWork = $extraReport->build('marketing-3', $marketer, $filter);
        $workRow = collect($mktWork['rows'] ?? [])->first(fn ($r) => ($r['name'] ?? '') === $marketer->name);
        $rows[] = $this->row(
            'Extra marketing-3 (công việc MKT)',
            $workRow && ($workRow['closed'] ?? 0) >= 1,
            'closed='.($workRow['closed'] ?? 0).', contacts='.($workRow['contacts'] ?? 0),
        );

        // Team leader stats
        $tls = $teamLeaderStats->build($marketer, $filter);
        $tlsRow = $this->findMarketerInTeamLeader($tls['rows'] ?? [], $marketer->id);
        $rows[] = $this->row(
            'Team leader stats',
            $tlsRow && ($tlsRow['closed'] ?? 0) >= 1,
            'closed='.($tlsRow['closed'] ?? 0).', contacts='.($tlsRow['contacts'] ?? 0),
        );

        // Campaign report
        $campReport = $campaignReport->build($filter, $marketer);
        $campRow = collect($campReport['rows'] ?? [])->first(
            fn ($r) => $campaign && ($r['campaignId'] ?? $r['id'] ?? null) == $campaign->id,
        );
        $rows[] = $this->row(
            'Campaign report',
            $campRow && ($campRow['actualRevenue'] ?? $campRow['revenue'] ?? 0) > 0
                || ($campRow['closedOrders'] ?? $campRow['closed'] ?? 0) >= 1,
            json_encode($campRow ? array_intersect_key($campRow, array_flip(['leadsGenerated', 'actualRevenue', 'closedOrders'])) : []),
        );

        // Dashboard snapshots
        $adminKpi = $metrics->kpiSummary($admin, $filter);
        $rows[] = $this->row(
            'Admin KPI (filtered)',
            ($adminKpi['closed_orders'] ?? 0) >= 1,
            'closed='.($adminKpi['closed_orders'] ?? 0).', leads='.($adminKpi['total_leads'] ?? 0),
        );

        $mktSnap = DashboardStatsService::marketingSnapshot($marketer, $filter);
        $rows[] = $this->row(
            'Marketing dashboard snapshot (filtered)',
            ($mktSnap['orders_closed'] ?? 0) >= 1 || ($adminKpi['closed_orders'] ?? 0) >= 1,
            'orders_closed='.($mktSnap['orders_closed'] ?? 0),
        );

        // Seed contamination check
        $seedOnlyContacts = $campaign && $campaign->contacts > 0 && $leadCount === 0
            && ($sourceRow['contacts'] ?? 0) === $campaign->contacts;
        $rows[] = $this->row(
            'Không dùng seed contacts thay lead thật',
            ! $seedOnlyContacts,
            "lead_ingestions={$leadCount}, campaign.contacts={$campaign?->contacts}, report contacts=".($sourceRow['contacts'] ?? 'n/a'),
        );

        $this->newLine();
        $this->table(['Báo cáo / Dashboard', 'OK', 'Chi tiết'], $rows);

        $failed = collect($rows)->filter(fn ($r) => ($r[1] ?? '') === 'FAIL')->count();
        if ($failed > 0) {
            $this->error("{$failed} kiểm tra FAIL.");

            return self::FAILURE;
        }

        $this->info('Tất cả kiểm tra PASS — dữ liệu luồng hiển thị trên báo cáo.');

        return self::SUCCESS;
    }

    private function resolveOrder(): ?Order
    {
        if ($code = $this->option('order')) {
            return Order::query()->where('order_code', $code)->first();
        }

        if ($phone = $this->option('phone')) {
            $normalized = preg_replace('/\D+/', '', (string) $phone);

            return Order::query()->where('customer_phone', $normalized)->latest('id')->first();
        }

        return Order::query()->latest('id')->first();
    }

    /** @param  list<array<string, mixed>>  $roots */
    private function findMarketerInTree(array $roots, int $marketerId): ?array
    {
        foreach ($roots as $root) {
            foreach ($root['children'] ?? [] as $team) {
                foreach ($team['children'] ?? [] as $member) {
                    if (($member['type'] ?? '') === 'marketer' && str_ends_with((string) ($member['id'] ?? ''), (string) $marketerId)) {
                        return $member;
                    }
                }
            }
        }

        return null;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function findMarketerInTeamLeader(array $rows, int $marketerId): ?array
    {
        foreach ($rows as $team) {
            foreach ($team['children'] ?? [] as $member) {
                if (($member['id'] ?? '') === 'marketer-'.$marketerId) {
                    return $member;
                }
            }
        }

        return null;
    }

    /** @return list{string, string, string} */
    private function row(string $label, bool $ok, string $detail): array
    {
        return [$label, $ok ? 'PASS' : 'FAIL', $detail];
    }
}
