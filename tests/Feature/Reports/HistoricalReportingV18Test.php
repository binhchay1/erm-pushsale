<?php

namespace Tests\Feature\Reports;

use App\Data\ReportFilterData;
use App\Enums\LeadIngestionStatus;
use App\Enums\LeadPacketType;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Reporting\ReportDailyClosure;
use App\Models\Reporting\ReportDailyLeadFact;
use App\Models\Reporting\ReportDailyOrderFact;
use App\Models\Reporting\ReportDailyProductFact;
use App\Models\Reporting\ReportDirtyDate;
use App\Models\Reporting\ReportQuerySnapshot;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Reporting\DailyReportAggregator;
use App\Services\Reporting\MonthlyArchiveService;
use App\Services\Reporting\ReportSnapshotStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HistoricalReportingV18Test extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_daily_facts_keep_one_lead_and_include_upsell_revenue_dimensions(): void
    {
        Carbon::setTestNow('2026-07-10 12:00:00');
        [$company, $admin, $order] = $this->makeBusinessDay('2026-07-09 09:00:00');

        $result = app(DailyReportAggregator::class)->rebuild($company->id, '2026-07-09', true);

        $this->assertSame('closed', $result['status']);
        $this->assertSame(2, (int) ReportDailyLeadFact::query()
            ->where('company_id', $company->id)
            ->whereDate('metric_date', '2026-07-09')
            ->sum('packet_count'));
        $this->assertSame(1, (int) ReportDailyLeadFact::query()
            ->where('company_id', $company->id)
            ->whereDate('metric_date', '2026-07-09')
            ->sum('lead_count'));

        $orderFact = ReportDailyOrderFact::query()
            ->where('company_id', $company->id)
            ->whereDate('metric_date', '2026-07-09')
            ->where('date_basis', 'data_arrival')
            ->firstOrFail();
        $this->assertSame(1, (int) $orderFact->order_count);
        $this->assertSame(1, (int) $orderFact->closed_order_count);
        $this->assertSame(0, (int) $orderFact->open_order_count);
        $this->assertSame(1, (int) $orderFact->upsell_order_count);
        $this->assertSame((int) $orderFact->shipping_cost, (int) $orderFact->closed_shipping_cost);

        $this->assertSame(1, (int) ReportDailyProductFact::query()
            ->where('company_id', $company->id)
            ->whereDate('metric_date', '2026-07-09')
            ->where('date_basis', 'data_arrival')
            ->where('is_upsell', true)
            ->sum('quantity'));

        $verification = app(DailyReportAggregator::class)->verify($company->id, '2026-07-09');
        $this->assertTrue($verification['valid']);
        $this->assertSame('closed', ReportDailyClosure::query()->firstOrFail()->status);
        $this->assertFalse(ReportDirtyDate::query()->where('company_id', $company->id)->exists());
    }

    public function test_late_change_reopens_day_invalidates_final_snapshot_and_is_rebuildable(): void
    {
        Carbon::setTestNow('2026-07-10 12:00:00');
        [$company, $admin, $order] = $this->makeBusinessDay('2026-07-09 09:00:00');
        $aggregator = app(DailyReportAggregator::class);
        $aggregator->rebuild($company->id, '2026-07-09', true);

        $filter = ReportFilterData::fromRequest(Request::create('/', 'GET', [
            'date_from' => '2026-07-09',
            'date_to' => '2026-07-09',
        ]), $admin);
        $calls = 0;
        $store = app(ReportSnapshotStore::class);
        $first = $store->remember('v18-test', $admin, $filter, function () use (&$calls): array {
            $calls++;
            return ['value' => 123];
        });
        $second = $store->remember('v18-test', $admin, $filter, function () use (&$calls): array {
            $calls++;
            return ['value' => 999];
        });

        $this->assertFalse($first['fromCache']);
        $this->assertTrue($first['isFinal']);
        $this->assertTrue($second['fromCache']);
        $this->assertSame(1, $calls);
        $this->assertSame(1, ReportQuerySnapshot::query()->count());

        $order->update(['carrier_service_fee' => 30_000]);

        $this->assertSame('dirty', ReportDailyClosure::query()
            ->where('company_id', $company->id)
            ->whereDate('metric_date', '2026-07-09')
            ->value('status'));
        $this->assertDatabaseHas('report_dirty_dates', [
            'company_id' => $company->id,
            'metric_date' => '2026-07-09',
        ]);
        $this->assertSame(0, ReportQuerySnapshot::query()->count());

        $aggregator->rebuild($company->id, '2026-07-09', true);
        $this->assertTrue($aggregator->verify($company->id, '2026-07-09')['valid']);
    }

    public function test_monthly_archive_copies_and_verifies_raw_rows_without_purging_by_default(): void
    {
        Carbon::setTestNow('2026-07-10 12:00:00');
        [$company, , $order] = $this->makeBusinessDay('2026-06-15 09:00:00');

        $result = app(MonthlyArchiveService::class)->archiveCompanyMonth($company->id, '2026-06');

        $this->assertTrue($result['lead_ingestions']['verified']);
        $this->assertTrue($result['orders']['verified']);
        $this->assertFalse($result['lead_ingestions']['sourcePurged']);
        $this->assertDatabaseHas('analytics_archive_manifests', [
            'company_id' => $company->id,
            'source_table' => 'lead_ingestions',
            'archive_month' => '2026-06',
            'verified' => true,
            'source_purged' => false,
        ]);
        $this->assertDatabaseHas('lead_ingestions', ['company_id' => $company->id]);

        $order->update(['carrier_service_fee' => 25_000]);
        $this->assertDatabaseHas('analytics_archive_manifests', [
            'company_id' => $company->id,
            'source_table' => 'orders',
            'archive_month' => '2026-06',
            'status' => 'stale',
            'verified' => false,
        ]);
    }

    /** @return array{Company,User,Order} */
    private function makeBusinessDay(string $timestamp): array
    {
        $previousNow = Carbon::getTestNow();
        Carbon::setTestNow(Carbon::parse($timestamp));

        $company = Company::query()->create([
            'name' => 'V18 Company',
            'slug' => Company::makeSlug('V18 Company'),
            'status' => Company::STATUS_ACTIVE,
            'plan' => 'test',
            'max_users' => 100,
        ]);
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
        ]);
        $sale = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Sales,
        ]);
        $marketer = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Marketing,
        ]);
        $warehouse = Warehouse::query()->create([
            'company_id' => $company->id,
            'name' => 'Kho V18',
        ]);
        $product = Product::query()->create([
            'company_id' => $company->id,
            'name' => 'Sản phẩm V18',
            'sku' => 'V18-MAIN',
            'unit_price' => 500_000,
            'cost_price' => 200_000,
            'is_active' => true,
        ]);
        $source = MarketingSource::query()->create([
            'company_id' => $company->id,
            'name' => 'Landing V18',
            'marketer_user_id' => $marketer->id,
            'webhook_token' => 'v18-source-'.uniqid(),
            'is_active' => true,
        ]);

        $at = Carbon::parse($timestamp);
        $order = Order::query()->create([
            'company_id' => $company->id,
            'order_code' => 'V18-'.str_replace([' ', ':', '-'], '', $timestamp).'-'.uniqid(),
            'sale_user_id' => $sale->id,
            'marketer_user_id' => $marketer->id,
            'marketing_source_id' => $source->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'customer_name' => 'Khách V18',
            'customer_phone' => '0900000018',
            'data_arrived_at' => $at,
            'assigned_at' => $at,
            'closed_at' => $at,
            'last_delivery_event_at' => $at,
            'desired_delivery_at' => $at,
            'operation_stage' => 'new_customer',
            'closing_status' => 'closed',
            'delivery_status' => 'delivered',
            'reconciliation_status' => 'settled',
            'subtotal' => 650_000,
            'discount' => 0,
            'vat' => 0,
            'shipping_fee_collected' => 0,
            'total' => 650_000,
            'amount_to_collect' => 650_000,
            'settled_cod_amount' => 650_000,
            'carrier_service_fee' => 20_000,
            'contact_count' => 1,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
        OrderItem::query()->create([
            'company_id' => $company->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Sản phẩm chính',
            'item_type' => 'main',
            'origin' => 'landing',
            'quantity' => 1,
            'unit_price' => 500_000,
            'cost_price' => 200_000,
            'discount_amount' => 0,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
        OrderItem::query()->create([
            'company_id' => $company->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Sản phẩm upsale',
            'item_type' => 'upsell',
            'origin' => 'upsell',
            'quantity' => 1,
            'unit_price' => 150_000,
            'cost_price' => 50_000,
            'discount_amount' => 0,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
        $primary = LeadIngestion::query()->create([
            'company_id' => $company->id,
            'platform' => 'landing',
            'external_id' => 'v18-main-'.uniqid(),
            'status' => LeadIngestionStatus::Processed,
            'packet_type' => LeadPacketType::Lead,
            'counts_as_lead' => true,
            'customer_phone' => $order->customer_phone,
            'marketing_source_id' => $source->id,
            'order_id' => $order->id,
            'payload' => [],
            'processed_at' => $at,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
        LeadIngestion::query()->create([
            'company_id' => $company->id,
            'platform' => 'landing',
            'external_id' => 'v18-upsell-'.uniqid(),
            'status' => LeadIngestionStatus::Processed,
            'packet_type' => LeadPacketType::Upsell,
            'counts_as_lead' => false,
            'customer_phone' => $order->customer_phone,
            'marketing_source_id' => $source->id,
            'order_id' => $order->id,
            'parent_ingestion_id' => $primary->id,
            'payload' => [],
            'processed_at' => $at,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        Carbon::setTestNow($previousNow);

        return [$company, $admin, $order];
    }
}
