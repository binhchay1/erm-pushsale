<?php

namespace Tests\Feature\Reports;

use App\Data\ReportFilterData;
use App\Enums\UserRole;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\User;
use App\Services\Reports\RevenueReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RevenueReportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_for_marketers_includes_kpi_without_type_error(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-10 12:00:00'));
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $marketer = User::factory()->create(['role' => UserRole::Marketing]);
        $campaign = MarketingSource::query()->create([
            'name' => 'Summer ads',
            'budget' => 500_000,
            'marketer_user_id' => $marketer->id,
            'utm_campaign' => 'summer',
            'webhook_token' => 'tok-summer',
            'is_active' => true,
        ]);
        Order::query()->create([
            'order_code' => 'ORD-REV-1',
            'marketer_user_id' => $marketer->id,
            'marketing_source_id' => $campaign->id,
            'customer_name' => 'Customer',
            'customer_phone' => '0900111222',
            'delivery_status' => 'delivered',
            'closed_at' => now(),
            'total' => 2_000_000,
            'discount' => 0,
            'carrier_service_fee' => 0,
            'cod_fee' => 0,
        ]);
        $filter = ReportFilterData::fromRequest(
            Request::create('/marketing/revenue', 'GET', ['preset' => 'today']),
            $admin,
        );

        $result = app(RevenueReportService::class)->forMarketers($filter, $admin);

        $this->assertGreaterThanOrEqual(2, count($result['rows']));
        $total = collect($result['rows'])->firstWhere('isTotalRow', true);
        $this->assertNotNull($total);
        $this->assertArrayHasKey('attributedRevenue', $total);
        $this->assertArrayHasKey('roas', $total);
    }

    public function test_for_sales_includes_upsell_metrics_in_closed_order_revenue(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-23 10:00:00'));

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $sale = User::factory()->create(['role' => UserRole::Sales, 'name' => 'Vũ Đức Long']);

        $order = Order::query()->create([
            'order_code' => 'ORD-UPSELL-REV-1',
            'sale_user_id' => $sale->id,
            'customer_name' => 'Khách upsale báo cáo',
            'customer_phone' => '0900999888',
            'delivery_status' => 'delivered',
            'data_arrived_at' => now(),
            'closed_at' => now(),
            'subtotal' => 458_000,
            'discount' => 0,
            'total' => 458_000,
            'carrier_service_fee' => 0,
            'cod_fee' => 0,
        ]);

        $order->items()->createMany([
            [
                'product_name' => 'Gói máy dán cao cấp',
                'item_type' => 'product',
                'origin' => 'landing_main',
                'quantity' => 1,
                'unit_price' => 299_000,
            ],
            [
                'product_name' => 'Gói máy dán — size nhỏ',
                'item_type' => 'upsell',
                'origin' => 'landing_upsell',
                'quantity' => 1,
                'unit_price' => 159_000,
            ],
        ]);

        $filter = ReportFilterData::fromRequest(
            Request::create('/admin/sales/revenue', 'GET', ['preset' => 'today']),
            $admin,
        );

        $result = app(RevenueReportService::class)->forSales($filter, $admin);
        $total = collect($result['rows'])->firstWhere('isTotalRow', true);
        $saleRow = collect($result['rows'])->firstWhere('saleId', (string) $sale->id);

        foreach ([$total, $saleRow] as $row) {
            $this->assertNotNull($row);
            $this->assertSame(1, (int) $row['closedOrders']['qty']);
            $this->assertSame(458_000, (int) $row['closedOrders']['revenue']);
            $this->assertSame(2, (int) $row['productCount']);
            $this->assertSame(1, (int) $row['upsellQuantity']);
            $this->assertSame(159_000, (int) $row['upsellRevenue']);
            $this->assertSame(34.7, (float) $row['upsellRevenueShare']);
        }
    }
}
