<?php

namespace Tests\Feature\Reports;

use App\Data\ReportFilterData;
use App\Enums\UserRole;
use App\Models\LeadIngestion;
use App\Models\Order;
use App\Models\User;
use App\Services\Reports\ReportQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_orders_query_applies_date_filter_and_role_scope(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-10 12:00:00'));
        $salesA = User::factory()->create(['role' => UserRole::Sales]);
        $salesB = User::factory()->create(['role' => UserRole::Sales]);
        $visible = $this->createOrder('ORD-VISIBLE', [
            'sale_user_id' => $salesA->id,
            'data_arrived_at' => '2026-06-09 10:00:00',
        ]);
        $this->createOrder('ORD-OUT-OF-RANGE', [
            'sale_user_id' => $salesA->id,
            'data_arrived_at' => '2026-05-01 10:00:00',
        ]);
        $this->createOrder('ORD-OTHER-SALE', [
            'sale_user_id' => $salesB->id,
            'data_arrived_at' => '2026-06-09 10:00:00',
        ]);
        $filter = ReportFilterData::fromRequest(Request::create('/reports', 'GET', ['preset' => 'last_7_days']), $salesA);

        $ids = app(ReportQueryService::class)->orders($salesA, $filter)->pluck('id')->all();

        $this->assertSame([$visible->id], $ids);
    }

    public function test_leads_query_includes_ladipage_fields_and_search(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-10 12:00:00'));
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $visible = $this->createLead('lp-1', [
            'platform' => 'landing',
            'utm_source' => 'ladipage',
            'utm_campaign' => 'summer-shirt',
            'created_at' => '2026-06-10 10:00:00',
        ]);
        $this->createLead('lp-2', [
            'platform' => 'landing',
            'utm_source' => 'ladipage',
            'utm_campaign' => 'winter-shirt',
            'created_at' => '2026-06-10 10:00:00',
        ]);
        $filter = ReportFilterData::fromRequest(Request::create('/reports', 'GET', [
            'preset' => 'today',
            'search' => 'summer',
        ]), $admin);

        $ids = app(ReportQueryService::class)->leads($admin, $filter)->pluck('id')->all();

        $this->assertSame([$visible->id], $ids);
    }

    public function test_grouped_queries_keep_scope(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-10 12:00:00'));
        $salesA = User::factory()->create(['role' => UserRole::Sales]);
        $salesB = User::factory()->create(['role' => UserRole::Sales]);
        $this->createOrder('ORD-GROUP-A', ['sale_user_id' => $salesA->id, 'total' => 1000, 'data_arrived_at' => now()]);
        $this->createOrder('ORD-GROUP-B', ['sale_user_id' => $salesB->id, 'total' => 9000, 'data_arrived_at' => now()]);
        $filter = ReportFilterData::fromRequest(Request::create('/reports', 'GET', ['preset' => 'today']), $salesA);

        $rows = app(ReportQueryService::class)->ordersGroupedBySale($salesA, $filter)->get();

        $this->assertCount(1, $rows);
        $this->assertSame($salesA->id, $rows->first()->sale_user_id);
        $this->assertSame(1000, (int) $rows->first()->revenue_total);
    }

    /** @param  array<string, mixed>  $attributes */
    private function createOrder(string $code, array $attributes = []): Order
    {
        return Order::query()->create(array_merge([
            'order_code' => $code,
            'customer_name' => 'Customer '.$code,
            'customer_phone' => '0900000000',
            'delivery_status' => 'waiting_waybill',
            'data_arrived_at' => now(),
        ], $attributes));
    }

    /** @param  array<string, mixed>  $attributes */
    private function createLead(string $externalId, array $attributes = []): LeadIngestion
    {
        return LeadIngestion::query()->create(array_merge([
            'platform' => 'landing',
            'external_id' => $externalId,
            'status' => 'processed',
            'customer_name' => 'Lead '.$externalId,
            'customer_phone' => '0900000000',
            'payload' => [],
            'created_at' => now(),
        ], $attributes));
    }
}
