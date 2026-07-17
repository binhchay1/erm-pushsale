<?php

namespace App\Console\Commands;

use App\Enums\ReconciliationStatus;
use App\Enums\UserRole;
use App\Jobs\Leads\ProcessLeadIngestionJob;
use App\Models\InboundEvent;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Marketing\CampaignLandingService;
use App\Services\Orders\OrderClosingService;
use App\Services\Reports\ReportMetricService;
use App\Services\Shipping\ShippingWebhookService;
use App\Support\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FlowE2eTestCommand extends Command
{
    protected $signature = 'e2e:flow-test {--phone=0912345678 : SĐT khách test}';

    protected $description = 'Chạy thử luồng E2E: chiến dịch → webhook landing → lead → chốt đơn → vận chuyển → đối soát → báo cáo';

    /** @var list<array{step:string, status:string, detail:string}> */
    private array $report = [];

    public function handle(
        CampaignLandingService $landing,
        OrderClosingService $closing,
        ShippingWebhookService $shippingWebhook,
        ReportMetricService $metrics,
    ): int {
        $phone = (string) $this->option('phone');
        $companyId = app(TenantManager::class)->id();

        $this->info('=== E2E FLOW TEST ===');
        $this->newLine();

        // ── Bước 1: Tạo chiến dịch ──
        $marketer = User::query()->where('email', 'marketing@saleops.local')->first();
        $product = Product::query()->where('sku', 'SP-GOI-01S')->first()
            ?? Product::query()->first();

        if (! $marketer || ! $product) {
            $this->failStep('1_campaign', 'Thiếu user marketing hoặc sản phẩm — chạy AccountSeeder + CatalogSeeder');

            return self::FAILURE;
        }

        $token = $landing->generateToken();
        $campaign = MarketingSource::query()->create([
            'name' => 'E2E Test Landing '.now()->format('Ymd-His'),
            'product_id' => $product->id,
            'marketer_user_id' => $marketer->id,
            'created_by_user_id' => $marketer->id,
            'ad_channel' => 'landing',
            'utm_source' => 'ladipage',
            'utm_campaign' => 'e2e-test-'.Str::lower(Str::random(6)),
            'webhook_token' => $token,
            'budget' => 5_000_000,
            'is_active' => true,
            'is_approved' => true,
            'company_id' => $companyId,
        ]);

        $webhookUrl = rtrim(config('app.url'), '/').'/api/v1/landing/'.$token.'/receive';
        $this->passStep('1_campaign', "Chiến dịch #{$campaign->id} — URL: {$webhookUrl}");

        // ── Bước 2: Gọi webhook landing (HTTP) ──
        $payload = [
            'submission_id' => 'e2e-'.uniqid(),
            'name' => 'Khách E2E Test',
            'phone' => $phone,
            'product' => $product->name,
            'quantity' => 1,
            'utm_source' => 'ladipage',
        ];

        $usedHttp = false;
        try {
            $response = Http::timeout(15)->post($webhookUrl, $payload);
            if ($response->successful() || $response->status() === 202) {
                $usedHttp = true;
                $this->waitQueueEmpty('webhooks');
            } else {
                $this->warn('  HTTP '.$response->status().' — fallback dispatch sync');
            }
        } catch (\Throwable) {
            $this->warn('  Server không chạy — dispatch job sync trực tiếp');
        }

        if (! $usedHttp) {
            ProcessLeadIngestionJob::dispatchSync(
                'landing',
                $payload,
                $campaign->id,
                $companyId,
            );
        }

        $normalizedPhone = preg_replace('/\D+/', '', $phone);
        $lead = LeadIngestion::query()
            ->where('counts_as_lead', true)
            ->where('customer_phone', $normalizedPhone)
            ->latest('id')
            ->first();

        $inbound = InboundEvent::query()->latest('id')->first();

        if (! $lead || $lead->status->value !== 'processed') {
            $this->failStep('2_webhook', 'Lead chưa processed — status: '.($lead?->status->value ?? 'null'));

            return self::FAILURE;
        }

        $this->passStep('2_webhook', "Lead #{$lead->id} processed — inbound event: ".($inbound?->status->value ?? 'n/a'));

        // ── Bước 3: Kiểm tra đơn tác nghiệp ──
        $order = Order::query()->find($lead->order_id);
        if (! $order) {
            $this->failStep('3_order', 'Không tạo được đơn từ lead');

            return self::FAILURE;
        }

        $this->passStep('3_order', "Đơn {$order->order_code} — sale: {$order->sale_user_id} — stage: {$order->operation_stage}");

        // ── Bước 4: Chốt đơn ──
        $saleUser = User::query()->find($order->sale_user_id) ?? $marketer;
        $warehouse = Warehouse::query()->first();

        $closing->close($order->fresh(), $saleUser, [
            'shipping_provider' => 'ghtk',
            'warehouse_id' => $warehouse?->id,
            'amount_to_collect' => (int) $product->unit_price,
            'shipping_address' => '123 Đường Test, Quận 1, TP.HCM',
            'confirm_insufficient_stock' => false,
        ]);

        $order->refresh();
        if (! $order->closed_at) {
            $this->failStep('4_close', 'Chốt đơn thất bại');

            return self::FAILURE;
        }

        $this->passStep('4_close', "Đã chốt — COD: {$order->amount_to_collect} — delivery: {$order->delivery_status}");

        // ── Bước 5: Tạo vận đơn (nếu GHTK sẵn sàng) ──
        $tracking = 'E2E'.strtoupper(Str::random(8));
        try {
            $this->waitQueueEmpty('shipments');
            $order->refresh();
            if ($order->tracking_number) {
                $tracking = $order->tracking_number;
                $this->passStep('5_shipment', "Vận đơn GHTK: {$tracking}");
            } else {
                $order->update(['tracking_number' => $tracking, 'shipping_provider' => 'ghtk']);
                $this->warnStep('5_shipment', "GHTK API không tạo VD (có thể thiếu token) — dùng tracking giả: {$tracking}");
            }
        } catch (\Throwable $e) {
            $order->update(['tracking_number' => $tracking, 'shipping_provider' => 'ghtk', 'delivery_status' => 'picking_up']);
            $this->warnStep('5_shipment', 'Bỏ qua API GHTK: '.$e->getMessage());
        }

        // ── Bước 6: Webhook vận chuyển — giao + paid ──
        $shippingWebhook->process('ghtk', [
            'label' => $tracking,
            'order_code' => $order->order_code,
            'status_id' => 6,
            'status_text' => 'Đã giao hàng',
            'cod' => $order->amount_to_collect,
        ]);

        $shippingWebhook->process('ghtk', [
            'label' => $tracking,
            'order_code' => $order->order_code,
            'status_text' => 'paid',
            'cod' => $order->amount_to_collect,
        ]);

        $order->refresh();
        $this->passStep('6_shipping_webhook', "delivery={$order->delivery_status} recon={$order->reconciliation_status} settled={$order->settled_cod_amount}");

        // ── Bước 7: Báo cáo ──
        $admin = User::query()->where('email', 'admin@saleops.local')->first();
        $filter = \App\Data\ReportFilterData::fromRequest(
            \Illuminate\Http\Request::create('/', 'GET', ['preset' => 'this_month']),
            $admin,
        );

        $kpi = $metrics->kpiSummary($admin, $filter);
        $closedCount = Order::query()->whereNotNull('closed_at')->count();
        $settledCount = Order::query()->whereIn('reconciliation_status', ReconciliationStatus::settledStatuses())->count();

        $this->passStep('7_reports', "Đơn chốt: {$closedCount} | Đã đối soát: {$settledCount} | KPI orders: ".($kpi['orders'] ?? '?'));

        if ($order->reconciliation_status !== ReconciliationStatus::Settled->value
            && $order->reconciliation_status !== ReconciliationStatus::Reconciled->value) {
            $this->warnStep('7_reconciliation', "Đơn E2E chưa settled — hiện: {$order->reconciliation_status}");
        } else {
            $this->passStep('7_reconciliation', 'Đối soát COD OK');
        }

        $this->newLine();
        $this->table(['Bước', 'Kết quả', 'Chi tiết'], collect($this->report)->map(fn ($r) => [$r['step'], $r['status'], $r['detail']])->all());

        $failed = collect($this->report)->contains(fn ($r) => $r['status'] === 'FAIL');

        return $failed ? self::FAILURE : self::SUCCESS;
    }


    private function waitQueueEmpty(string $queue): void
    {
        if (config('queue.default') !== 'redis') {
            return;
        }

        Artisan::call('queue:wait-empty', ['--queue' => [$queue], '--timeout' => 60]);
    }

    private function passStep(string $step, string $detail): void
    {
        $this->report[] = ['step' => $step, 'status' => 'OK', 'detail' => $detail];
        $this->line("<fg=green>✓</> {$step}: {$detail}");
    }

    private function warnStep(string $step, string $detail): void
    {
        $this->report[] = ['step' => $step, 'status' => 'WARN', 'detail' => $detail];
        $this->line("<fg=yellow>!</> {$step}: {$detail}");
    }

    private function failStep(string $step, string $detail): void
    {
        $this->report[] = ['step' => $step, 'status' => 'FAIL', 'detail' => $detail];
        $this->error("✗ {$step}: {$detail}");
    }
}
