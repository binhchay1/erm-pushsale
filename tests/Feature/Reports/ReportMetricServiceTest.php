<?php

namespace Tests\Feature\Reports;

use App\Data\ReportFilterData;
use App\Enums\LeadIngestionStatus;
use App\Enums\UserRole;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\User;
use App\Services\Reports\ReportMetricService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportMetricServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_kpi_summary_and_funnel_use_scoped_queries(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-10 12:00:00'));
        $salesA = User::factory()->create(['role' => UserRole::Sales]);
        $salesB = User::factory()->create(['role' => UserRole::Sales]);
        $orderA = $this->createOrder('ORD-METRIC-A', [
            'sale_user_id' => $salesA->id,
            'closed_at' => now(),
            'delivery_status' => 'paid',
            'total' => 2_000_000,
        ]);
        $orderB = $this->createOrder('ORD-METRIC-B', ['sale_user_id' => $salesB->id, 'total' => 9_000_000]);
        $this->createLead('lead-a', ['order_id' => $orderA->id, 'status' => LeadIngestionStatus::Processed->value]);
        $this->createLead('lead-b', ['order_id' => $orderB->id, 'status' => LeadIngestionStatus::Processed->value]);
        $filter = ReportFilterData::fromRequest(Request::create('/reports', 'GET', ['preset' => 'today']), $salesA);

        $service = app(ReportMetricService::class);
        $summary = $service->kpiSummary($salesA, $filter);
        $funnel = $service->funnel($salesA, $filter);

        $this->assertSame(1, $summary['leads']);
        $this->assertSame(1, $summary['orders']);
        $this->assertSame(2_000_000, $summary['revenue']);
        $this->assertSame(100.0, $summary['conversion_rate']);
        $this->assertSame('Lead', $funnel[0]['label']);
        $this->assertSame(1, $funnel[0]['value']);
    }

    public function test_series_and_source_breakdown_include_ladipage_metrics(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-10 12:00:00'));
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $source = MarketingSource::query()->create([
            'name' => 'Landing Campaign',
            'utm_source' => 'landing',
            'ad_channel' => 'landing',
        ]);
        $this->createOrder('ORD-LANDING', [
            'marketing_source_id' => $source->id,
            'delivery_status' => 'paid',
            'total' => 1_000_000,
        ]);
        $this->createLead('lead-landing', [
            'platform' => 'landing',
            'utm_source' => 'ladipage',
        ]);
        $filter = ReportFilterData::fromRequest(Request::create('/reports', 'GET', ['preset' => 'today']), $admin);

        $service = app(ReportMetricService::class);
        $leadSources = $service->leadSourceBreakdown($admin, $filter);
        $revenueSeries = $service->revenueSeries($admin, $filter);

        $this->assertSame('landing', $leadSources[0]['name']);
        $this->assertSame(1, $leadSources[0]['value']);
        $this->assertSame(1_000_000, $revenueSeries[0]['value']);
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
            'contact_count' => 1,
        ], $attributes));
    }

    /** @param  array<string, mixed>  $attributes */
    private function createLead(string $externalId, array $attributes = []): LeadIngestion
    {
        return LeadIngestion::query()->create(array_merge([
            'platform' => 'landing',
            'external_id' => $externalId,
            'status' => LeadIngestionStatus::Processed->value,
            'customer_name' => 'Lead '.$externalId,
            'customer_phone' => '0900000000',
            'payload' => [],
            'created_at' => now(),
        ], $attributes));
    }
}
