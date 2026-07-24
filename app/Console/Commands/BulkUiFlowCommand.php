<?php

namespace App\Console\Commands;

use App\Enums\ClosingStatus;
use App\Enums\DeliveryStatus;
use App\Enums\LeadAllocationMode;
use App\Enums\OperationResult;
use App\Enums\OperationStage;
use App\Enums\UserRole;
use App\Http\Controllers\Admin\LandingApprovalController;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Leads\LeadAllocationModeService;
use App\Services\Marketing\CampaignLandingService;
use App\Services\Operations\SaleOperationStatusService;
use App\Services\Orders\OrderClosingService;
use App\Services\Shipping\ShippingWebhookService;
use App\Support\TenantManager;
use Database\Seeders\FlowDataResetSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Seed dữ liệu luồng phức tạp: nhiều chiến dịch × nhiều lead qua webhook HTTP thật,
 * gán đúng company_id để admin/sale thấy trên UI (tenant scope).
 */
class BulkUiFlowCommand extends Command
{
    protected $signature = 'demo:bulk-flow
                            {--reset : Xóa dữ liệu luồng trước khi tạo}
                            {--campaigns=2 : Số chiến dịch đã duyệt}
                            {--per-campaign=20 : Số lead mỗi chiến dịch}
                            {--pending-campaign=1 : Tạo thêm chiến dịch chờ duyệt (0 để tắt)}';

    protected $description = 'Tạo dữ liệu demo lớn (webhook HTTP) — 2 chiến dịch × 20 khách, đủ pipeline cho báo cáo';

    /** @var list<string> */
    private const FLOW_TABLES_WITH_COMPANY = [
        'marketing_sources',
        'lead_ingestions',
        'orders',
        'order_items',
        'inbound_events',
        'shipments',
        'shipping_webhook_events',
        'shipping_api_logs',
        'carrier_settlement_batches',
        'carrier_settlement_lines',
        'failed_partner_orders',
        'user_notifications',
        'warehouse_inventory_movements',
    ];

    public function handle(
        CampaignLandingService $landing,
        LeadAllocationModeService $allocationMode,
        SaleOperationStatusService $operationStatus,
        OrderClosingService $closing,
        ShippingWebhookService $shippingWebhook,
        TenantManager $tenant,
    ): int {
        if ($this->option('reset')) {
            $this->call('db:seed', ['--class' => FlowDataResetSeeder::class, '--force' => true]);
        }

        $admin = User::query()->where('email', 'admin@saleops.local')->first();
        $marketer = User::query()->where('email', 'marketing@saleops.local')->first();
        $warehouse = Warehouse::query()->first();

        if (! $admin || ! $marketer || ! $warehouse) {
            $this->error('Thiếu user admin/marketing/kho — chạy php artisan db:seed trước.');

            return self::FAILURE;
        }

        $companyId = (int) ($admin->company_id ?? $marketer->company_id ?? 1);
        $this->backfillCompanyId($companyId);
        $allocationMode->set(LeadAllocationMode::Auto);

        $products = Product::query()->orderBy('id')->limit(5)->get();
        if ($products->isEmpty()) {
            $this->error('Chưa có sản phẩm trong catalog.');

            return self::FAILURE;
        }

        $campaignCount = max(1, (int) $this->option('campaigns'));
        $perCampaign = max(1, (int) $this->option('per-campaign'));
        $pendingCampaigns = max(0, (int) $this->option('pending-campaign'));

        $this->info("Company #{$companyId} — {$campaignCount} chiến dịch × {$perCampaign} lead (+ {$pendingCampaigns} chờ duyệt)");

        $createdCampaigns = [];

        $tenant->forCompany($companyId, function () use (
            $landing,
            $marketer,
            $products,
            $campaignCount,
            $perCampaign,
            $pendingCampaigns,
            &$createdCampaigns,
        ) {
            for ($c = 1; $c <= $campaignCount; $c++) {
                $product = $products[($c - 1) % $products->count()];
                $data = $landing->prepareForCreate([
                    'name' => "Bulk Demo {$c} — {$product->name}",
                    'product_id' => $product->id,
                    'marketer_user_id' => $marketer->id,
                    'ad_channel' => 'landing',
                    'utm_source' => 'ladipage',
                    'budget' => 5_000_000 + ($c * 1_000_000),
                ], $marketer->id);

                $campaign = MarketingSource::query()->create($data);
                app(LandingApprovalController::class)->approve($campaign->fresh());
                $createdCampaigns[] = ['campaign' => $campaign->fresh(), 'product' => $product, 'index' => $c];
            }

            for ($p = 1; $p <= $pendingCampaigns; $p++) {
                $product = $products[($p + $campaignCount) % $products->count()];
                $data = $landing->prepareForCreate([
                    'name' => "Bulk Demo — Chờ duyệt #{$p}",
                    'product_id' => $product->id,
                    'marketer_user_id' => $marketer->id,
                    'ad_channel' => 'landing',
                    'utm_source' => 'ladipage',
                    'budget' => 2_000_000,
                ], $marketer->id);

                $createdCampaigns[] = ['campaign' => MarketingSource::query()->create($data), 'product' => $product, 'index' => 100 + $p, 'pending' => true];
            }
        });

        $baseUrl = rtrim(config('app.url'), '/');
        $webhookOk = 0;
        $webhookFail = 0;

        foreach ($createdCampaigns as $bundle) {
            /** @var MarketingSource $campaign */
            $campaign = $bundle['campaign'];
            $isPending = (bool) ($bundle['pending'] ?? false);
            $count = $isPending ? min(5, $perCampaign) : $perCampaign;
            $url = $baseUrl.'/api/v1/landing/'.$campaign->webhook_token.'/receive';

            for ($i = 1; $i <= $count; $i++) {
                $prefix = $isPending ? 'P' : (string) $bundle['index'];
                $phone = '09'.str_pad((string) (20000000 + ($bundle['index'] * 100) + $i), 8, '0', STR_PAD_LEFT);

                try {
                    $response = Http::timeout(20)->acceptJson()->post($url, [
                        'submission_id' => "bulk-{$prefix}-{$i}-".Str::lower(Str::random(6)),
                        'name' => "Khách {$prefix}-".str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                        'phone' => $phone,
                        'product' => $bundle['product']->name,
                        'quantity' => 1 + ($i % 3),
                        'utm_source' => 'ladipage',
                        'utm_campaign' => $campaign->utm_campaign,
                    ]);

                    if ($response->successful() || $response->status() === 202) {
                        $webhookOk++;
                    } else {
                        $webhookFail++;
                    }
                } catch (\Throwable) {
                    $webhookFail++;
                }
            }

            $this->line(($isPending ? '○' : '✓')." Chiến dịch #{$campaign->id} {$campaign->name} — {$count} webhook");
        }

        if ($webhookFail > 0) {
            $this->warn("{$webhookFail} webhook lỗi — cần `php artisan serve` đang chạy.");
        }

        Artisan::call('queue:wait-empty', ['--queue' => ['webhooks'], '--timeout' => 120]);
        $this->info("Queue xử lý xong — {$webhookOk} lead đã gửi.");

        $orders = Order::query()->orderBy('id')->get();
        $this->info('Đơn tạo được: '.$orders->count());

        $scenarioIndex = 0;
        foreach ($orders as $order) {
            $sale = User::query()->find($order->sale_user_id);
            if (! $sale) {
                continue;
            }

            $this->applyScenario(
                $order,
                $sale,
                $warehouse,
                $this->scenarios()[$scenarioIndex % count($this->scenarios())],
                $operationStatus,
                $closing,
                $shippingWebhook,
            );
            $scenarioIndex++;
        }

        $this->newLine();
        $this->info('=== DỮ LIỆU SẴN SÀNG TRÊN UI ===');
        $this->table(['Màn hình', 'URL', 'Ghi chú'], [
            ['Duyệt Landing', '/admin/landing-approvals', 'Có chiến dịch chờ duyệt + đã duyệt'],
            ['Phân bổ data', '/admin/leads', 'Pushsale-style data distribution'],
            ['Báo cáo công việc sale', '/admin/sales/reports/work', 'Filter: 7 ngày / tháng này'],
            ['BC chốt đơn', '/admin/sales/reports/closing-summary', ''],
            ['BC doanh số sale', '/admin/sales/reports/revenue-detail', ''],
            ['Workspace telesale', '/sales/workspace', 'Đăng nhập sale được gán đơn'],
            ['Đối soát COD', '/admin/shipping/reconciliation', ''],
            ['Dashboard CEO', '/admin/dashboard', ''],
        ]);
        $this->line('Đăng nhập: admin@saleops.local / password');

        return self::SUCCESS;
    }

    private function backfillCompanyId(int $companyId): void
    {
        foreach (self::FLOW_TABLES_WITH_COMPANY as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'company_id')) {
                continue;
            }

            $updated = DB::table($table)->whereNull('company_id')->update(['company_id' => $companyId]);
            if ($updated > 0) {
                $this->line("Backfill {$table}: {$updated} dòng → company_id={$companyId}");
            }
        }
    }

    /** @return list<array<string, mixed>> */
    private function scenarios(): array
    {
        return [
            ['stage' => OperationStage::NewCustomer, 'contact' => 0, 'result' => OperationResult::NoContact],
            ['stage' => OperationStage::NewCustomer, 'contact' => 2, 'result' => OperationResult::NoContact],
            ['stage' => OperationStage::Call2, 'contact' => 1, 'result' => OperationResult::NoAnswer1],
            ['stage' => OperationStage::Call3, 'contact' => 2, 'result' => OperationResult::NoAnswer2],
            ['stage' => OperationStage::Call4, 'contact' => 3, 'result' => OperationResult::Considering],
            ['stage' => OperationStage::Call5, 'contact' => 3, 'result' => OperationResult::SentQuote],
            ['stage' => OperationStage::Call6, 'contact' => 4, 'result' => OperationResult::ReadyToClose],
            ['stage' => OperationStage::Call3, 'contact' => 2, 'result' => OperationResult::CallbackScheduled, 'callback' => true],
            ['close' => true, 'delivery' => DeliveryStatus::WaitingWaybill],
            ['close' => true, 'delivery' => DeliveryStatus::Delivering, 'tracking' => true],
            ['close' => true, 'delivery' => DeliveryStatus::Delivered, 'tracking' => true],
            ['close' => true, 'delivery' => DeliveryStatus::Paid, 'tracking' => true, 'settle' => true],
            ['terminal' => OperationResult::WrongNumber],
            ['terminal' => OperationResult::NoNeed],
            ['terminal' => OperationResult::PriceRejected],
            ['stage' => OperationStage::Call2, 'contact' => 1, 'result' => OperationResult::NoAnswer1],
            ['stage' => OperationStage::Call4, 'contact' => 2, 'result' => OperationResult::SentQuote],
            ['close' => true, 'delivery' => DeliveryStatus::Returned, 'tracking' => true],
            ['stage' => OperationStage::Call5, 'contact' => 3, 'result' => OperationResult::Considering],
            ['close' => true, 'delivery' => DeliveryStatus::Paid, 'tracking' => true, 'settle' => true],
        ];
    }

    /** @param  array<string, mixed>  $scenario */
    private function applyScenario(
        Order $order,
        User $sale,
        Warehouse $warehouse,
        array $scenario,
        SaleOperationStatusService $operationStatus,
        OrderClosingService $closing,
        ShippingWebhookService $shippingWebhook,
    ): void {
        $order = $order->fresh();

        if (isset($scenario['terminal'])) {
            /** @var OperationResult $result */
            $result = $scenario['terminal'];
            $order->update([
                'operation_result' => $result->value,
                'operation_stage' => OperationStage::Skipped->value,
                'closing_status' => ClosingStatus::Cancelled->value,
                'contact_count' => max(1, (int) $order->contact_count),
            ]);

            return;
        }

        if (! empty($scenario['close'])) {
            if (! $order->closed_at) {
                $closing->close($order, $sale, [
                    'shipping_provider' => 'ghtk',
                    'warehouse_id' => $warehouse->id,
                    'amount_to_collect' => (int) $order->total,
                    'shipping_address' => 'Địa chỉ demo bulk flow, TP.HCM',
                    'confirm_insufficient_stock' => false,
                ]);
                $order = $order->fresh();
            }

            /** @var DeliveryStatus $delivery */
            $delivery = $scenario['delivery'];
            $tracking = null;

            if (! empty($scenario['tracking'])) {
                $tracking = 'BD'.strtoupper(Str::random(8));
                $order->update([
                    'tracking_number' => $tracking,
                    'shipping_provider' => 'ghtk',
                ]);
            }

            $order->update(['delivery_status' => $delivery->value]);

            if ($tracking && in_array($delivery, [DeliveryStatus::Delivered, DeliveryStatus::Paid], true)) {
                $shippingWebhook->process('ghtk', [
                    'label' => $tracking,
                    'order_code' => $order->order_code,
                    'status_id' => 6,
                    'status_text' => 'Đã giao hàng',
                    'cod' => $order->amount_to_collect,
                ]);
            }

            if ($tracking && ! empty($scenario['settle'])) {
                $shippingWebhook->process('ghtk', [
                    'label' => $tracking,
                    'order_code' => $order->order_code,
                    'status_text' => 'paid',
                    'cod' => $order->amount_to_collect,
                ]);
            }

            return;
        }

        if (! empty($scenario['callback'])) {
            $operationStatus->applyStatus($order, $sale, [
                'operation_result' => OperationResult::CallbackScheduled->value,
                'next_operation_at' => now()->addDays(1 + ($order->id % 3))->format('Y-m-d H:i:s'),
                'note' => 'Hẹn gọi lại — bulk demo',
            ]);

            return;
        }

        $order->update([
            'operation_stage' => $scenario['stage']->value,
            'operation_result' => $scenario['result']->value,
            'contact_count' => (int) ($scenario['contact'] ?? 1),
            'closing_status' => ClosingStatus::Open->value,
        ]);
    }
}
