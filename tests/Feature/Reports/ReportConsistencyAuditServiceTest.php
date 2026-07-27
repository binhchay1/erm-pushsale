<?php

namespace Tests\Feature\Reports;

use App\Data\ReportFilterData;
use App\Enums\LeadIngestionStatus;
use App\Enums\UserRole;
use App\Models\LeadIngestion;
use App\Models\Order;
use App\Models\User;
use App\Services\Reports\ReportConsistencyAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportConsistencyAuditServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_audit_passes_when_kpi_matches_live_orders_and_leads(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-10 12:00:00'));
        config(['reporting.enabled' => false]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Order::query()->create([
            'order_code' => 'ORD-AUDIT-1',
            'customer_name' => 'Audit Customer',
            'customer_phone' => '0900000001',
            'delivery_status' => 'paid',
            'data_arrived_at' => now(),
            'closed_at' => now(),
            'contact_count' => 1,
            'total' => 1_500_000,
        ]);
        LeadIngestion::query()->create([
            'platform' => 'landing',
            'external_id' => 'audit-lead-1',
            'status' => LeadIngestionStatus::Processed->value,
            'counts_as_lead' => true,
            'customer_name' => 'Audit Lead',
            'customer_phone' => '0900000001',
            'payload' => [],
            'created_at' => now(),
        ]);
        LeadIngestion::query()->create([
            'platform' => 'landing',
            'external_id' => 'audit-lead-fail',
            'status' => LeadIngestionStatus::Failed->value,
            'counts_as_lead' => true,
            'customer_name' => 'Failed Lead',
            'customer_phone' => '0900000002',
            'payload' => [],
            'created_at' => now(),
        ]);

        $filter = ReportFilterData::fromRequest(Request::create('/report-audit', 'GET', [
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
            'date_type' => 'data_arrival',
        ]), $admin);

        $snapshot = app(ReportConsistencyAuditService::class)->snapshot($admin, $filter);

        $this->assertSame('pass', $snapshot['status'], json_encode($snapshot['rows'], JSON_UNESCAPED_UNICODE));
        $this->assertSame(0, $snapshot['failed']);
    }
}
