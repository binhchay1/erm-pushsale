<?php

namespace Tests\Feature\Reports;

use App\Data\ReportFilterData;
use App\Enums\UserRole;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Reports\ExtraReportService;
use App\Services\Reports\HourlyStatsService;
use App\Services\Reports\TeamLeaderStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MarketingExtraReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function seedClosedOrderWithUpsell(): array
    {
        Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00'));
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $marketer = User::factory()->create(['role' => UserRole::Marketing]);
        $sale = User::factory()->create(['role' => UserRole::Sales]);

        $source = MarketingSource::query()->create([
            'name' => 'Landing combo',
            'ad_channel' => 'facebook',
            'marketer_user_id' => $marketer->id,
            'utm_campaign' => 'combo',
            'webhook_token' => 'tok-combo',
            'is_active' => true,
        ]);

        $order = Order::query()->create([
            'order_code' => 'ORD-UP-1',
            'marketer_user_id' => $marketer->id,
            'sale_user_id' => $sale->id,
            'marketing_source_id' => $source->id,
            'customer_name' => 'KH',
            'customer_phone' => '0900111000',
            'closing_status' => 'closed',
            'delivery_status' => 'delivered',
            'data_arrived_at' => now(),
            'closed_at' => now()->addHour(),
            'total' => 900_000,
            'discount' => 0,
            'carrier_service_fee' => 0,
            'cod_fee' => 0,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_name' => 'Main product',
            'item_type' => 'main',
            'quantity' => 1,
            'unit_price' => 600_000,
            'discount_amount' => 0,
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_name' => 'Upsell product',
            'item_type' => 'upsell',
            'quantity' => 2,
            'unit_price' => 150_000,
            'discount_amount' => 0,
        ]);

        return [$admin, $source];
    }

    public function test_marketing_work_report_builds(): void
    {
        [$admin] = $this->seedClosedOrderWithUpsell();
        $filter = ReportFilterData::fromRequest(Request::create('/x', 'GET', ['preset' => 'today']), $admin);

        $data = app(ExtraReportService::class)->build('marketing-3', $admin, $filter);

        $this->assertNotEmpty($data['rows']);
        $this->assertSame('marketing_work_matrix', $data['extra']['mode']);
        $this->assertNotEmpty($data['extra']['salesColumns']);
        $this->assertNotEmpty($data['extra']['matrixRows']);

        $row = $data['extra']['matrixRows'][0];
        $this->assertSame(1, $row['contacts']);
        $this->assertSame(1, $row['closed']);
        $this->assertSame(100.0, $row['rate']);
        $this->assertSame(1, $row['sale_cells'][0]['contacts']);
        $this->assertSame(100.0, $row['sale_cells'][0]['rate']);
        $this->assertSame(1, $data['totals']['contacts']);
    }

    public function test_upsale_report_separates_upsell_metrics(): void
    {
        [$admin] = $this->seedClosedOrderWithUpsell();
        $filter = ReportFilterData::fromRequest(Request::create('/x', 'GET', ['preset' => 'today']), $admin);

        $data = app(ExtraReportService::class)->build('marketing-4', $admin, $filter);

        $row = $data['rows'][0];
        $this->assertSame('Landing combo', $row['name']);
        $this->assertSame('Facebook ads', $row['channel']);
        $this->assertStringContainsString('Main product', $row['products']);
        $this->assertStringContainsString('Upsell product', $row['products']);
        $this->assertSame(1, $row['contacts']);
        $this->assertSame(1, $row['closed']);
        $this->assertSame(1.0, $row['rate_decimal']);
        $this->assertStringContainsString('/customers?source_id=', $row['detail_url']);
        $this->assertSame(2, $row['product_types']);
        $this->assertSame(3, $row['qty_sold']);
        $this->assertSame(2, $row['upsell_qty']);
        $this->assertSame(300_000, $row['upsell_rev']);
        $this->assertSame('marketing_upsale_source', $data['extra']['mode']);
        $this->assertSame('CHI TIẾT ĐƠN HÀNG TỪ NGUỒN DỮ LIỆU (12)', $data['columns'][11]['label'] ?? '');
    }

    public function test_revenue_detail_funnel_columns(): void
    {
        [$admin] = $this->seedClosedOrderWithUpsell();
        $filter = ReportFilterData::fromRequest(Request::create('/x', 'GET', ['preset' => 'today']), $admin);

        $data = app(ExtraReportService::class)->build('marketing-1', $admin, $filter);
        $row = $data['rows'][0];

        $this->assertSame(1, $row['closed_qty']);
        $this->assertSame(900_000, $row['closed_rev']);
        $this->assertSame(1, $row['xngh_qty']);
        $this->assertSame(1, $row['transfer_qty']);
        $this->assertSame(1, $row['delivered_qty']);
        $this->assertSame(1, $row['success_qty']);
        $this->assertSame(3, $row['product_count']);
        $this->assertSame(100.0, $row['pct_success']);
        $this->assertSame(0.0, $row['pct_returned']);
    }

    public function test_team_leader_stats_groups_by_team(): void
    {
        [$admin] = $this->seedClosedOrderWithUpsell();
        $filter = ReportFilterData::fromRequest(Request::create('/x', 'GET', ['preset' => 'today']), $admin);

        $data = app(TeamLeaderStatsService::class)->build($admin, $filter);

        $this->assertNotEmpty($data['rows']);
        $team = $data['rows'][0];
        $this->assertTrue($team['isTeam']);
        $this->assertSame(1, $team['closed']);
        $this->assertSame(900_000, $team['revenueTotal']);
        $this->assertNotEmpty($team['children']);
    }

    public function test_hourly_stats_buckets_by_hour(): void
    {
        [$admin] = $this->seedClosedOrderWithUpsell();
        $filter = ReportFilterData::fromRequest(Request::create('/x', 'GET', ['preset' => 'today']), $admin);

        $data = app(HourlyStatsService::class)->build($admin, $filter);

        $this->assertCount(24, $data['rows']);
        $this->assertSame(1, $data['totals']['contacts']);
        $this->assertSame(1, $data['totals']['closed']);
        // contact came in at 09h, closed at 10h
        $this->assertSame(1, $data['rows'][9]['contacts']);
        $this->assertSame(1, $data['rows'][10]['closed']);
    }
}
