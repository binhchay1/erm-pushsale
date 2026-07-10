<?php

namespace Tests\Feature\Reports;

use App\Data\ReportFilterData;
use App\Enums\ClosingStatus;
use App\Enums\DeliveryStatus;
use App\Enums\LeadIngestionStatus;
use App\Enums\LeadPacketType;
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

    public function test_parent_campaign_aggregates_child_source_without_counting_supplemental_order_as_lead(): void
    {
        Carbon::setTestNow('2026-07-08 10:00:00');

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $marketer = User::factory()->create(['role' => UserRole::Marketing]);
        $parent = MarketingSource::query()->create([
            'name' => 'Parent campaign',
            'marketer_user_id' => $marketer->id,
            'creator_user_id' => $admin->id,
            'webhook_token' => 'parent-campaign-token',
            'is_active' => true,
            'budget' => 500_000,
        ]);
        $child = MarketingSource::query()->create([
            'parent_id' => $parent->id,
            'name' => 'Child landing',
            'marketer_user_id' => $marketer->id,
            'creator_user_id' => $admin->id,
            'webhook_token' => 'child-campaign-token',
            'is_active' => true,
            'budget' => 200_000,
        ]);

        $baseOrder = Order::query()->create([
            'order_code' => 'ORD-FAMILY-BASE',
            'marketer_user_id' => $marketer->id,
            'marketing_source_id' => $child->id,
            'customer_name' => 'Family customer',
            'customer_phone' => '0900111222',
            'data_arrived_at' => now(),
            'closed_at' => now(),
            'closing_status' => ClosingStatus::Closed->value,
            'delivery_status' => DeliveryStatus::Delivered->value,
            'total' => 100_000,
        ]);
        $baseLead = LeadIngestion::query()->create([
            'platform' => 'landing',
            'external_id' => 'family-base',
            'status' => LeadIngestionStatus::Processed,
            'packet_type' => LeadPacketType::Lead,
            'counts_as_lead' => true,
            'customer_phone' => $baseOrder->customer_phone,
            'marketing_source_id' => $child->id,
            'order_id' => $baseOrder->id,
            'payload' => [],
            'processed_at' => now(),
        ]);

        $supplementalOrder = Order::query()->create([
            'order_code' => 'ORD-FAMILY-SUPPLEMENT',
            'marketer_user_id' => $marketer->id,
            'marketing_source_id' => $child->id,
            'customer_name' => 'Family customer',
            'customer_phone' => '0900111222',
            'data_arrived_at' => now(),
            'closed_at' => now(),
            'closing_status' => ClosingStatus::Closed->value,
            'delivery_status' => DeliveryStatus::Delivered->value,
            'is_returning_customer' => true,
            'total' => 50_000,
        ]);
        LeadIngestion::query()->create([
            'platform' => 'landing',
            'external_id' => 'family-supplement:upsell',
            'status' => LeadIngestionStatus::Processed,
            'packet_type' => LeadPacketType::LateUpsell,
            'counts_as_lead' => false,
            'customer_phone' => $baseOrder->customer_phone,
            'marketing_source_id' => $child->id,
            'parent_ingestion_id' => $baseLead->id,
            'order_id' => $supplementalOrder->id,
            'related_order_id' => $baseOrder->id,
            'payload' => [],
            'processed_at' => now(),
        ]);

        $filter = new ReportFilterData(
            dateFrom: now()->startOfDay(),
            dateTo: now()->endOfDay(),
        );

        $dashboard = app(MarketingDashboardService::class)->build($filter);
        $parentRow = collect($dashboard['rows'])->firstWhere('id', (string) $parent->id);
        $childRow = collect($dashboard['rows'])->firstWhere('id', (string) $child->id);

        $this->assertSame(1, (int) $parentRow['contacts']);
        $this->assertSame(1, (int) $childRow['contacts']);
        $this->assertSame(1, (int) $dashboard['filterTotal']['contacts']);
        $this->assertSame(150_000, (int) $parentRow['totalRevenue']);

        $campaign = app(MarketingCampaignReportService::class)->build($filter, $admin);
        $campaignRow = collect($campaign['rows'])->firstWhere('campaignId', (string) $parent->id);
        $this->assertSame(1, (int) $campaignRow['leadsGenerated']);
        $this->assertSame(150_000, (int) $campaignRow['actualRevenue']);

        $ceo = app(CeoReportService::class)->build($filter, $admin);
        $this->assertSame(1, (int) ($ceo['marketingRows'][0]['contacts'] ?? 0));
        $this->assertSame(150_000, (int) ($ceo['marketingRows'][0]['totalEstRevenue'] ?? 0));
    }

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
            'packet_type' => LeadPacketType::Upsell,
            'counts_as_lead' => false,
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
            'packet_type' => LeadPacketType::Lead,
            'counts_as_lead' => true,
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
