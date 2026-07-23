<?php

namespace Tests\Feature\Reports;

use App\Data\ReportFilterData;
use App\Enums\DeliveryStatus;
use App\Enums\LeadIngestionStatus;
use App\Enums\LeadPacketType;
use App\Enums\OrgLevel;
use App\Enums\UserRole;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\NavigationService;
use App\Services\Reports\ExtraReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TemplateSixReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_template_six_report_registry_and_role_levels_are_explicit(): void
    {
        $registry = ExtraReportService::registry();

        $this->assertArrayHasKey('warehouse-sales-summary', $registry);
        $this->assertArrayHasKey('warehouse-sales-v2', $registry);
        $this->assertArrayHasKey('product-conversion', $registry);
        $this->assertSame('leader', $registry['product-conversion']['level']);
        $this->assertContains('discount_mode', $registry['marketing-1']['filters']);
        $this->assertContains('marketing_team_leader_id', $registry['marketing-1']['filters']);
        $this->assertContains('delivery_status', $registry['marketing-1']['filters']);
        $this->assertContains('parent_product_id', $registry['sale-3']['filters']);

        $staff = User::factory()->create([
            'role' => UserRole::Sales,
            'org_level' => OrgLevel::Staff,
            'is_team_leader' => false,
        ]);
        $leader = User::factory()->create([
            'role' => UserRole::Sales,
            'org_level' => OrgLevel::Supervisor,
            'is_team_leader' => true,
        ]);

        $service = app(ExtraReportService::class);
        $this->assertFalse($service->canView($staff, 'warehouse-sales-summary'));
        $this->assertTrue($service->canView($leader, 'warehouse-sales-summary'));
        $this->assertContains(DeliveryStatus::PartialDelivery->value, DeliveryStatus::revenueEligible());
    }

    public function test_admin_marketing_report_menu_keeps_pushsale_order(): void
    {
        $marketing = collect(config('pushsale_navigation'))->firstWhere('title', '2. Marketing');
        $reports = collect($marketing['children'] ?? [])->firstWhere('title', '2.7 Báo cáo');
        $items = collect($reports['children'] ?? [])->map(fn (array $item): string => $item['title'])->values()->all();

        $this->assertSame([
            '1. Báo cáo doanh số marketing',
            '2. Báo cáo doanh số',
            '3. Báo cáo doanh số V2',
            '4. CEO Dashboard V2',
            '5. Báo cáo công việc',
            '6. Báo cáo kinh doanh hệ thống',
        ], $items);
    }

    public function test_shared_reports_are_mounted_under_each_role_menu_without_leaking_the_admin_tree(): void
    {
        $salesStaff = User::factory()->create([
            'role' => UserRole::Sales,
            'org_level' => OrgLevel::Staff,
            'is_team_leader' => false,
        ]);
        $salesLeader = User::factory()->create([
            'role' => UserRole::Sales,
            'org_level' => OrgLevel::Supervisor,
            'is_team_leader' => true,
        ]);
        $marketingLeader = User::factory()->create([
            'role' => UserRole::Marketing,
            'org_level' => OrgLevel::Supervisor,
            'is_team_leader' => true,
        ]);
        $warehouseStaff = User::factory()->create([
            'role' => UserRole::Warehouse,
            'org_level' => OrgLevel::Staff,
        ]);
        $accountingStaff = User::factory()->create([
            'role' => UserRole::Accounting,
            'org_level' => OrgLevel::Staff,
        ]);

        $reports = app(ExtraReportService::class);
        $this->assertTrue($reports->canView($warehouseStaff, 'warehouse-sales-summary'));
        $this->assertTrue($reports->canView($warehouseStaff, 'warehouse-sales-v2'));
        $this->assertTrue($reports->canView($accountingStaff, 'sale-2'));
        $this->assertTrue($reports->canView($accountingStaff, 'marketing-4'));

        $navigation = app(NavigationService::class);

        $salesStaffUrls = $this->navigationUrls($navigation->forUser($salesStaff));
        $this->assertContains('/sales/reports/sale-2', $salesStaffUrls);
        $this->assertNotContains('/sales/reports/warehouse-sales-summary', $salesStaffUrls);
        $this->assertNotContains('/sales/reports/product-conversion', $salesStaffUrls);

        $salesLeaderUrls = $this->navigationUrls($navigation->forUser($salesLeader));
        $this->assertContains('/sales/reports/warehouse-sales-summary', $salesLeaderUrls);
        $this->assertContains('/sales/reports/warehouse-sales-v2', $salesLeaderUrls);
        $this->assertContains('/sales/reports/product-conversion', $salesLeaderUrls);

        $marketingUrls = $this->navigationUrls($navigation->forUser($marketingLeader));
        $this->assertContains('/marketing/reports/marketing-1', $marketingUrls);
        $this->assertContains('/marketing/reports/product-conversion', $marketingUrls);
        $this->assertContains('/marketing/reports/marketing-4', $marketingUrls);
        $this->assertNotContains('/sales/reports/sale-2', $marketingUrls);

        $warehouseUrls = $this->navigationUrls($navigation->forUser($warehouseStaff));
        $this->assertContains('/warehouse/reports/warehouse-sales-summary', $warehouseUrls);
        $this->assertContains('/warehouse/reports/warehouse-sales-v2', $warehouseUrls);
        $this->assertNotContains('/warehouse/reports/product-conversion', $warehouseUrls);

        $accountingUrls = $this->navigationUrls($navigation->forUser($accountingStaff));
        $this->assertContains('/accounting/reports/sale-2', $accountingUrls);
        $this->assertContains('/accounting/reports/marketing-1', $accountingUrls);
        $this->assertContains('/accounting/reports/marketing-4', $accountingUrls);
        $this->assertContains('/accounting/reports/product-conversion', $accountingUrls);

        foreach ([$salesStaffUrls, $salesLeaderUrls, $marketingUrls, $warehouseUrls, $accountingUrls] as $urls) {
            $this->assertSame([], array_values(array_filter(
                $urls,
                static fn (string $url): bool => str_starts_with($url, '/admin/reports/extra/'),
            )));
        }
    }

    public function test_reports_count_one_contact_but_keep_supplemental_upsell_revenue(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 09:00:00'));
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $sale = User::factory()->create(['role' => UserRole::Sales]);
        $marketer = User::factory()->create(['role' => UserRole::Marketing]);
        $warehouse = Warehouse::query()->create(['name' => 'Kho Hà Nội']);
        $source = MarketingSource::query()->create([
            'name' => 'Landing chính + upsale',
            'marketer_user_id' => $marketer->id,
            'webhook_token' => 'template-six-source-token',
            'is_active' => true,
        ]);

        $base = Order::query()->create([
            'order_code' => 'TS-BASE',
            'sale_user_id' => $sale->id,
            'marketer_user_id' => $marketer->id,
            'marketing_source_id' => $source->id,
            'warehouse_id' => $warehouse->id,
            'customer_name' => 'Khách A',
            'customer_phone' => '0900000001',
            'data_arrived_at' => now(),
            'closed_at' => now(),
            'closing_status' => 'closed',
            'delivery_status' => 'delivered',
            'reconciliation_status' => 'settled',
            'is_returning_customer' => false,
            'total' => 900_000,
        ]);
        OrderItem::query()->create([
            'order_id' => $base->id,
            'product_name' => 'Sản phẩm chính',
            'item_type' => 'main',
            'quantity' => 1,
            'unit_price' => 600_000,
        ]);
        OrderItem::query()->create([
            'order_id' => $base->id,
            'product_name' => 'Sản phẩm upsale',
            'item_type' => 'upsell',
            'quantity' => 2,
            'unit_price' => 150_000,
        ]);
        $primaryLead = LeadIngestion::query()->create([
            'platform' => 'landing',
            'external_id' => 'ts-base',
            'status' => LeadIngestionStatus::Processed,
            'packet_type' => LeadPacketType::Lead,
            'counts_as_lead' => true,
            'customer_phone' => $base->customer_phone,
            'marketing_source_id' => $source->id,
            'order_id' => $base->id,
            'payload' => [],
            'processed_at' => now(),
        ]);

        $supplemental = Order::query()->create([
            'order_code' => 'TS-SUPPLEMENTAL',
            'sale_user_id' => $sale->id,
            'marketer_user_id' => $marketer->id,
            'marketing_source_id' => $source->id,
            'warehouse_id' => $warehouse->id,
            'customer_name' => 'Khách A',
            'customer_phone' => '0900000001',
            'data_arrived_at' => now(),
            'closed_at' => now(),
            'closing_status' => 'closed',
            'delivery_status' => 'partial_delivery',
            'reconciliation_status' => 'pending',
            'is_returning_customer' => true,
            'total' => 150_000,
        ]);
        OrderItem::query()->create([
            'order_id' => $supplemental->id,
            'product_name' => 'Sản phẩm upsale',
            'item_type' => 'upsell',
            'quantity' => 1,
            'unit_price' => 150_000,
        ]);
        LeadIngestion::query()->create([
            'platform' => 'landing',
            'external_id' => 'ts-supplemental',
            'status' => LeadIngestionStatus::Processed,
            'packet_type' => LeadPacketType::LateUpsell,
            'counts_as_lead' => false,
            'customer_phone' => $base->customer_phone,
            'marketing_source_id' => $source->id,
            'order_id' => $supplemental->id,
            'related_order_id' => $base->id,
            'parent_ingestion_id' => $primaryLead->id,
            'payload' => [],
            'processed_at' => now(),
        ]);

        $filter = ReportFilterData::fromRequest(Request::create('/reports', 'GET', ['preset' => 'today']), $admin);
        $reports = app(ExtraReportService::class);

        $closing = $reports->build('sale-2', $admin, $filter)['rows'][0];
        $this->assertSame(1, $closing['new_contacts']);
        $this->assertSame(1, $closing['total_closed']);
        $this->assertSame(1_050_000, $closing['total_net']);
        $this->assertSame(450_000, $closing['upsell_revenue']);

        $revenueDetailData = $reports->build('sale-3', $admin, $filter);
        $revenueDetail = $revenueDetailData['rows'][0];
        $this->assertSame(1, $revenueDetail['contacts']);
        $this->assertSame(2, $revenueDetail['closed_qty']);
        $this->assertSame(100.0, $revenueDetail['close_rate']);
        $this->assertSame(100.0, $revenueDetailData['totals']['close_rate']);
        $this->assertSame(450_000, $revenueDetail['upsell_rev']);

        $marketingRevenueDetail = $reports->build('marketing-1', $admin, $filter)['rows'][0];
        $this->assertSame(1, $marketingRevenueDetail['contacts']);
        $this->assertSame(2, $marketingRevenueDetail['closed_qty']);
        $this->assertSame(1_050_000, $marketingRevenueDetail['closed_rev']);
        $this->assertSame(450_000, $marketingRevenueDetail['upsell_rev']);
        $this->assertSame(42.9, $marketingRevenueDetail['upsell_revenue_share']);

        $warehouseV2 = $reports->build('warehouse-sales-v2', $admin, $filter)['rows'][0];
        $this->assertSame(1, $warehouseV2['contacts']);
        $this->assertSame(1, $warehouseV2['closed_contacts']);
        $this->assertSame(2, $warehouseV2['total_orders']);
        $this->assertSame(4, $warehouseV2['total_products']);
        $this->assertSame(1_050_000, $warehouseV2['total_revenue']);
        $this->assertSame(2, $warehouseV2['success_orders']);
        $this->assertSame(1, $warehouseV2['actual_orders']);
        $this->assertSame(1, $warehouseV2['pending_reconciliation_orders']);
        $this->assertSame(1, $warehouseV2['partial_orders']);
        $this->assertSame(450_000, $warehouseV2['upsell_revenue']);

        $warehouseSummaryData = $reports->build('warehouse-sales-summary', $admin, $filter);
        $warehouseSummary = $warehouseSummaryData['rows'][0];
        $this->assertCount(12, $warehouseSummaryData['extra']['revenueGroups']);
        $this->assertSame(2, $warehouseSummary['total_orders']);
        $this->assertSame(4, $warehouseSummary['total_products']);
        $this->assertSame(2.0, $warehouseSummary['total_products_per_order']);
        $this->assertSame(1, $warehouseSummary['partial_orders']);

        $systemBusiness = $reports->build('kho-2', $admin, $filter);
        $systemRow = $systemBusiness['rows'][0];
        $this->assertSame(1, $systemRow['active_warehouses']);
        $this->assertSame(2, $systemRow['closed_qty']);
        $this->assertSame(1_050_000, $systemRow['revenue']);
        $this->assertSame(1, $systemRow['new_phone_count']);
        $this->assertSame(900_000, $systemRow['new_rev']);
        $this->assertSame(1, $systemRow['old_phone_count']);
        $this->assertSame(150_000, $systemRow['old_rev']);
        $this->assertSame(3, $systemRow['upsell_qty']);
        $this->assertSame(450_000, $systemRow['upsell_revenue']);
        $this->assertSame(42.9, $systemRow['upsell_share']);

        $matrix = collect($reports->build('product-conversion', $admin, $filter)['rows'])
            ->firstWhere('name', 'Sản phẩm upsale');
        $this->assertSame(1, $matrix['contacts']);
        $this->assertSame(1, $matrix['closed']);
        $this->assertSame(450_000, $matrix['revenue']);
        $this->assertSame(450_000, $matrix['upsell_revenue']);
    }

    /** @param list<array<string, mixed>> $items @return list<string> */
    private function navigationUrls(array $items): array
    {
        $urls = [];

        foreach ($items as $item) {
            if (isset($item['url']) && is_string($item['url'])) {
                $urls[] = $item['url'];
            }

            if (! empty($item['children']) && is_array($item['children'])) {
                array_push($urls, ...$this->navigationUrls($item['children']));
            }
        }

        return array_values(array_unique($urls));
    }

    public function test_leader_filter_is_not_forced_to_their_own_user_id(): void
    {
        $leader = User::factory()->create([
            'role' => UserRole::Sales,
            'org_level' => OrgLevel::Supervisor,
            'is_team_leader' => true,
        ]);
        $member = User::factory()->create([
            'role' => UserRole::Sales,
            'manager_user_id' => $leader->id,
            'team_id' => $leader->team_id,
        ]);

        $allTeam = ReportFilterData::fromRequest(Request::create('/reports'), $leader);
        $specific = ReportFilterData::fromRequest(Request::create('/reports', 'GET', ['sale_id' => $member->id]), $leader);

        $this->assertNull($allTeam->saleId);
        $this->assertSame($member->id, $specific->saleId);
    }
}
