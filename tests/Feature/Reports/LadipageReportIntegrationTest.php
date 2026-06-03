<?php

namespace Tests\Feature\Reports;

use App\Data\ReportFilterData;
use App\Enums\IntegrationPlatform;
use App\Enums\LeadIngestionStatus;
use App\Enums\UserRole;
use App\Models\IntegrationConnection;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\User;
use App\Services\Reports\ReportMetricService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LadipageReportIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_ladipage_direct_payload_ingests_and_appears_in_report_metrics(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-10 12:00:00'));
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        User::factory()->create(['role' => UserRole::Sales]);
        $this->enableLandingIntegration();

        $this->postJson('/api/v1/webhooks/ladipage?api_key=secret-key', [
            'submission_id' => 'lp-direct-1',
            'name' => 'Nguyễn Văn Lead',
            'phone' => '0900000001',
            'product' => 'Áo thun',
            'utm_source' => 'ladipage',
            'utm_campaign' => 'summer-shirt',
        ])->assertCreated();

        $filter = ReportFilterData::fromRequest(Request::create('/reports', 'GET', ['preset' => 'today']), $admin);
        $summary = app(ReportMetricService::class)->kpiSummary($admin, $filter);
        $sources = app(ReportMetricService::class)->leadSourceBreakdown($admin, $filter);

        $this->assertSame(1, LeadIngestion::query()->where('platform', 'landing')->count());
        $this->assertSame(1, Order::query()->where('customer_phone', '0900000001')->count());
        $this->assertSame(1, $summary['leads']);
        $this->assertSame('landing', $sources[0]['name']);
    }

    public function test_ladipage_fields_payload_is_normalized(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-10 12:00:00'));
        User::factory()->create(['role' => UserRole::Sales]);
        $this->enableLandingIntegration();

        $this->postJson('/api/v1/webhooks/ladipage?api_key=secret-key', [
            'lead_id' => 'lp-fields-1',
            'fields' => [
                ['name' => 'ho_ten', 'value' => 'Trần Field'],
                ['name' => 'sdt', 'value' => '091.222.3333'],
                ['name' => 'san_pham', 'value' => 'Quần jeans'],
                ['name' => 'utm_campaign', 'value' => 'denim'],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('lead_ingestions', [
            'external_id' => 'lp-fields-1',
            'customer_name' => 'Trần Field',
            'customer_phone' => '0912223333',
            'utm_campaign' => 'denim',
        ]);
    }

    public function test_ladipage_f_field_payload_is_normalized(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-10 12:00:00'));
        User::factory()->create(['role' => UserRole::Sales]);
        $this->enableLandingIntegration();

        $this->postJson('/api/v1/webhooks/ladipage?api_key=secret-key', [
            'form_response_id' => 'lp-f-1',
            'f1' => 'Lê F Field',
            'f2' => '0988887777',
            'f3' => 'Váy công sở',
            'utm_source' => 'ladipage',
        ])->assertCreated();

        $lead = LeadIngestion::query()->where('external_id', 'lp-f-1')->firstOrFail();

        $this->assertSame(LeadIngestionStatus::Processed, $lead->status);
        $this->assertSame('0988887777', $lead->customer_phone);
    }

    public function test_campaign_webhook_assigns_sale_when_approved(): void
    {
        User::factory()->create(['role' => UserRole::Sales]);
        $marketer = User::factory()->create(['role' => UserRole::Marketing]);

        $campaign = MarketingSource::query()->create([
            'name' => 'Landing Test',
            'utm_campaign' => 'landing-test',
            'utm_source' => 'ladipage',
            'webhook_token' => 'testtoken123456789012345678901234',
            'created_by_user_id' => $marketer->id,
            'marketer_user_id' => $marketer->id,
            'is_active' => true,
            'is_approved' => true,
        ]);

        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/receive', [
            'submission_id' => 'lp-campaign-1',
            'name' => 'Khách Campaign',
            'phone' => '0901111222',
            'message' => 'Ghi chú test',
            'products' => 'Serum',
            'quantity' => 2,
        ])->assertCreated();

        $order = Order::query()->where('customer_phone', '0901111222')->first();
        $this->assertNotNull($order);
        $this->assertNotNull($order->sale_user_id);
        $this->assertSame($campaign->id, $order->marketing_source_id);
    }

    public function test_campaign_webhook_without_approval_does_not_assign_sale(): void
    {
        User::factory()->create(['role' => UserRole::Sales]);
        $marketer = User::factory()->create(['role' => UserRole::Marketing]);

        $campaign = MarketingSource::query()->create([
            'name' => 'Landing Pending',
            'utm_campaign' => 'landing-pending',
            'webhook_token' => 'pendingtoken1234567890123456789012',
            'created_by_user_id' => $marketer->id,
            'is_active' => true,
            'is_approved' => false,
        ]);

        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/receive', [
            'submission_id' => 'lp-pending-1',
            'name' => 'Khách Pending',
            'phone' => '0903333444',
        ])->assertCreated();

        $order = Order::query()->where('customer_phone', '0903333444')->first();
        $this->assertNotNull($order);
        $this->assertNull($order->sale_user_id);
    }

    private function enableLandingIntegration(): void
    {
        IntegrationConnection::query()->create([
            'platform' => IntegrationPlatform::Landing->value,
            'is_enabled' => true,
            'credentials' => ['api_key' => 'secret-key'],
        ]);
    }
}
