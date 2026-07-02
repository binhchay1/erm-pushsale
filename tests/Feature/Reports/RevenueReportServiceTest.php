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
}
