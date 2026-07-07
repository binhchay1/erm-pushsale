<?php

namespace Tests\Feature\Reports;

use App\Data\ReportFilterData;
use App\Enums\LeadIngestionStatus;
use App\Enums\UserRole;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\User;
use App\Services\Reports\CeoReportService;
use App\Services\Reports\ExtraReportService;
use App\Services\Reports\MarketingCampaignReportService;
use App\Services\Reports\MarketingDashboardService;
use App\Support\LeadContactMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Bảo đảm MỌI báo cáo đếm "contact" giống hệt nhau và bằng số lead thật:
 * - Không cộng số lần gọi (contact_count).
 * - Không cộng dòng duplicate / :upsell audit.
 */
class ReportContactParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_reports_report_identical_contact_count(): void
    {
        Carbon::setTestNow('2026-07-07 10:00:00');

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $marketer = User::factory()->create(['role' => UserRole::Marketing]);
        $source = MarketingSource::query()->create([
            'name' => 'Parity campaign',
            'marketer_user_id' => $marketer->id,
            'creator_user_id' => $admin->id,
            'webhook_token' => 'tok-parity',
            'is_active' => true,
            'budget' => 1_000_000,
        ]);

        // 3 khách thật -> 3 đơn, mỗi đơn đã gọi 3 lần (contact_count = 3).
        foreach (range(1, 3) as $i) {
            $phone = '090000000'.$i;
            $order = Order::query()->create([
                'order_code' => 'ORD-P'.$i,
                'marketer_user_id' => $marketer->id,
                'marketing_source_id' => $source->id,
                'customer_name' => 'Customer '.$i,
                'customer_phone' => $phone,
                'data_arrived_at' => now(),
                'contact_count' => 3,
                'total' => 100_000,
            ]);

            LeadIngestion::query()->create([
                'platform' => 'landing',
                'external_id' => 'sub-'.$i,
                'status' => LeadIngestionStatus::Processed,
                'customer_phone' => $phone,
                'marketing_source_id' => $source->id,
                'order_id' => $order->id,
                'payload' => [],
                'processed_at' => now(),
            ]);
        }

        // Dòng audit upsell của khách 1 (KHÔNG tính là contact mới).
        LeadIngestion::query()->create([
            'platform' => 'landing',
            'external_id' => 'sub-1:upsell',
            'status' => LeadIngestionStatus::Processed,
            'customer_phone' => '0900000001',
            'marketing_source_id' => $source->id,
            'order_id' => Order::query()->where('order_code', 'ORD-P1')->value('id'),
            'payload' => [],
            'processed_at' => now(),
        ]);

        // Lead trùng (KHÔNG tính).
        LeadIngestion::query()->create([
            'platform' => 'landing',
            'external_id' => 'sub-dup',
            'status' => LeadIngestionStatus::Duplicate,
            'customer_phone' => '0900000009',
            'marketing_source_id' => $source->id,
            'payload' => [],
        ]);

        $filter = new ReportFilterData(
            dateFrom: now()->startOfDay(),
            dateTo: now()->endOfDay(),
        );

        $expected = 3;

        // Nguồn chân lý
        $this->assertSame($expected, LeadContactMetrics::countToday(), 'countToday');
        $this->assertSame($expected, (int) LeadContactMetrics::countsBySource($filter)->get($source->id), 'countsBySource');
        $this->assertSame($expected, (int) LeadContactMetrics::countsByMarketer($filter)->get($marketer->id), 'countsByMarketer');

        // Marketing Dashboard (KPI + bảng nguồn + cây team)
        $mkt = app(MarketingDashboardService::class)->build($filter);
        $this->assertSame($expected, (int) $mkt['kpis']['contacts'], 'marketing dashboard kpi contacts');

        $treeContacts = 0;
        $walk = function (array $nodes) use (&$walk, &$treeContacts): void {
            foreach ($nodes as $node) {
                if (($node['type'] ?? null) === 'marketer') {
                    $treeContacts += (int) ($node['contacts'] ?? 0);
                }
                if (! empty($node['children'])) {
                    $walk($node['children']);
                }
            }
        };
        $walk($mkt['teamTree']['roots'] ?? []);
        $this->assertSame($expected, $treeContacts, 'marketing dashboard team tree contacts');

        // Báo cáo công việc marketing (marketing-3)
        $work = app(ExtraReportService::class)->build('marketing-3', $admin, $filter);
        $this->assertSame($expected, (int) ($work['totals']['contacts'] ?? 0), 'marketing-3 total contacts');

        // CEO report
        $ceo = app(CeoReportService::class)->build($filter, $admin);
        $ceoContacts = array_sum(array_map(fn ($r) => (int) ($r['contacts'] ?? 0), $ceo['marketingRows'] ?? []));
        $this->assertSame($expected, $ceoContacts, 'ceo marketing contacts');

        // Campaign report (leadsGenerated)
        $campaign = app(MarketingCampaignReportService::class)->build($filter, $admin);
        $campaignLeads = (int) collect($campaign['rows'])->firstWhere('isTotalRow', true)['leadsGenerated'];
        $this->assertSame($expected, $campaignLeads, 'campaign report leads generated');
    }
}
