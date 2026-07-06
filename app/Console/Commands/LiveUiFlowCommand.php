<?php

namespace App\Console\Commands;

use App\Enums\LeadAllocationMode;
use App\Enums\ReconciliationStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Sales\OrderClosingController;
use App\Http\Controllers\Sales\SaleOperationCallController;
use App\Http\Controllers\Sales\SaleOperationStatusController;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Leads\LeadAllocationModeService;
use App\Services\Marketing\CampaignApprovalService;
use App\Services\Marketing\CampaignLandingService;
use App\Services\Shipping\ShippingWebhookService;
use App\Support\TenantManager;
use Database\Seeders\FlowDataResetSeeder;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Tạo một luồng dữ liệu thật để xem trên UI local:
 * Marketing tạo chiến dịch → Admin duyệt → Webhook HTTP → queue → Telesale gọi/cập nhật trạng thái/chốt → webhook ship.
 */
class LiveUiFlowCommand extends Command
{
    protected $signature = 'demo:ui-flow
                            {--phone=0911888999 : SĐT khách demo}
                            {--reset : Xóa dữ liệu luồng trước khi tạo}
                            {--skip-ship : Bỏ webhook giao hàng / đối soát}';

    protected $description = 'Tạo 1 luồng nghiệp vụ thật trên DB local để xem trên http://127.0.0.1:8000';

    public function handle(
        CampaignLandingService $landing,
        LeadAllocationModeService $allocationMode,
        ShippingWebhookService $shippingWebhook,
    ): int {
        if ($this->option('reset')) {
            $this->call('db:seed', ['--class' => FlowDataResetSeeder::class, '--force' => true]);
        }

        $allocationMode->set(LeadAllocationMode::Auto);
        $this->info('Chế độ chia số: tự động');

        $phone = preg_replace('/\D+/', '', (string) $this->option('phone'));
        $tenant = app(TenantManager::class);

        $marketer = User::query()->where('email', 'marketing@saleops.local')->first();
        $admin = User::query()->where('email', 'admin@saleops.local')->first();
        $companyId = (int) ($admin?->company_id ?? $marketer?->company_id ?? 1);
        $product = Product::query()->where('sku', 'SP-GOI-01S')->first()
            ?? Product::query()->first();
        $warehouse = Warehouse::query()->first();

        if (! $marketer || ! $admin || ! $product) {
            $this->error('Thiếu user marketing/admin hoặc sản phẩm — chạy db:seed trước.');

            return self::FAILURE;
        }

        // ── Marketing: tạo chiến dịch (giống CampaignController@store) ──
        $campaignName = 'Demo UI Luồng Thật '.now()->format('d/m H:i');
        $campaign = $tenant->forCompany($companyId, function () use ($landing, $marketer, $product, $campaignName) {
            $campaignData = $landing->prepareForCreate([
                'name' => $campaignName,
                'product_id' => $product->id,
                'marketer_user_id' => $marketer->id,
                'ad_channel' => 'landing',
                'utm_source' => 'ladipage',
                'budget' => 3_000_000,
            ], $marketer->id);

            return MarketingSource::query()->create($campaignData);
        });
        $webhookUrl = rtrim(config('app.url'), '/').'/api/v1/landing/'.$campaign->webhook_token.'/receive';
        $this->line("✓ Marketing tạo chiến dịch #{$campaign->id} — chờ duyệt");

        // ── Admin: duyệt chiến dịch ──
        $this->actAs($admin, function () use ($admin, $campaign, $product) {
            app(CampaignApprovalService::class)->approve($admin, $campaign->fresh(), $product->id);
        });
        $campaign->refresh();
        $this->line('✓ Admin duyệt chiến dịch');

        // ── Ladipage: gửi lead qua HTTP thật ──
        $payload = [
            'submission_id' => 'ui-demo-'.Str::lower(Str::random(8)),
            'name' => 'Nguyễn Văn Demo UI',
            'phone' => $phone,
            'product' => $product->name,
            'quantity' => 1,
            'utm_source' => 'ladipage',
            'message' => 'Khách đăng ký từ landing demo — xem trên UI',
        ];

        try {
            $response = Http::timeout(20)->acceptJson()->post($webhookUrl, $payload);
            if (! $response->successful() && $response->status() !== 202) {
                $this->warn("Webhook HTTP {$response->status()} — đảm bảo `php artisan serve` đang chạy.");
                $this->warn($response->body());

                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            $this->error('Không gọi được webhook: '.$e->getMessage());
            $this->line('Chạy `php artisan serve` rồi thử lại.');

            return self::FAILURE;
        }

        Artisan::call('queue:work', ['--stop-when-empty' => true, '--max-jobs' => 20]);
        $this->line('✓ Webhook landing → đơn tạo & chia số ngay (cửa sổ upsale trên tác nghiệp)');

        $order = Order::query()
            ->where('customer_phone', $phone)
            ->latest('id')
            ->first();

        if (! $order) {
            $this->error('Chưa tạo được đơn — kiểm tra lead ingestion / chia số.');

            return self::FAILURE;
        }

        $saleUser = User::query()->find($order->sale_user_id);
        $this->line("✓ Đơn {$order->order_code} → Sale: ".($saleUser?->name ?? '?')." ({$saleUser?->email})");

        if (! $saleUser) {
            $this->error('Đơn chưa được gán sale — kiểm tra chế độ chia số.');

            return self::FAILURE;
        }

        // ── Telesale: Gọi + cập nhật trạng thái + chốt (cùng controller như UI) ──
        $this->actAs($saleUser, function () use ($order, $saleUser) {
            $req = $this->salesRequest($saleUser, "/sales/orders/{$order->id}/call", 'POST');
            app(SaleOperationCallController::class)->store($req, $order->fresh(), app(\App\Services\Operations\SaleOperationStatusService::class));
        });
        $order->refresh();
        $this->line("✓ Telesale bấm Gọi — contact_count={$order->contact_count}");

        $this->actAs($saleUser, function () use ($order, $saleUser) {
            $req = $this->salesRequest($saleUser, "/sales/orders/{$order->id}/operation-status", 'POST', [
                'operation_result' => 'sent_quote',
                'note' => 'Đã báo giá qua điện thoại',
            ]);
            app(SaleOperationStatusController::class)->update(
                $req,
                $order->fresh(),
                app(\App\Services\Operations\SaleOperationStatusService::class),
            );
        });
        $order->refresh();
        $this->line("✓ Telesale cập nhật trạng thái: {$order->operation_result} → stage {$order->operation_stage}");

        $this->actAs($saleUser, function () use ($order, $saleUser, $product, $warehouse) {
            $req = $this->salesRequest($saleUser, "/sales/orders/{$order->id}/close", 'POST', [
                'shipping_provider' => 'ghtk',
                'warehouse_id' => $warehouse?->id,
                'amount_to_collect' => (int) $product->unit_price,
                'shipping_address' => '88 Nguyễn Huệ, Quận 1, TP.HCM',
                'confirm_insufficient_stock' => false,
            ]);
            app(OrderClosingController::class)->store(
                $req,
                $order->fresh(),
                app(\App\Services\Orders\OrderClosingService::class),
            );
        });

        Artisan::call('queue:work', ['--stop-when-empty' => true, '--max-jobs' => 10]);
        $order->refresh();
        $this->line("✓ Telesale chốt đơn — COD {$order->amount_to_collect} — delivery={$order->delivery_status}");

        if (! $this->option('skip-ship')) {
            $tracking = $order->tracking_number ?: ('DEMO'.strtoupper(Str::random(8)));
            if (! $order->tracking_number) {
                $order->update(['tracking_number' => $tracking, 'shipping_provider' => 'ghtk']);
            }

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
            $this->line("✓ Webhook ship — recon={$order->reconciliation_status} settled={$order->settled_cod_amount}");
        }

        $this->newLine();
        $this->info('=== XEM TRÊN GIAO DIỆN (đăng nhập password: password) ===');
        $this->table(['Vai trò', 'Email', 'URL'], [
            ['Marketing', 'marketing@saleops.local', '/marketing/campaigns'],
            ['Admin', 'admin@saleops.local', '/admin/landing-approvals'],
            ['Admin', 'admin@saleops.local', '/admin/leads'],
            ['Telesale', $saleUser->email, '/sales/workspace'],
            ['Telesale', $saleUser->email, '/sales/dashboard'],
            ['Kho', 'warehouse@saleops.local', '/warehouse/workspace'],
            ['Kế toán', 'accounting@saleops.local', '/accounting/workspace'],
            ['Admin', 'admin@saleops.local', '/admin/shipping/reconciliation'],
            ['Admin', 'admin@saleops.local', '/admin/reports/business'],
            ['Admin', 'admin@saleops.local', '/admin/dashboard'],
        ]);

        $this->newLine();
        $this->line("Khách: Nguyễn Văn Demo UI · {$phone}");
        $this->line("Đơn: {$order->order_code} · Chiến dịch: {$campaignName}");
        $this->line("Webhook đã dùng: {$webhookUrl}");

        $settled = in_array($order->reconciliation_status, [
            ReconciliationStatus::Settled->value,
            ReconciliationStatus::Reconciled->value,
        ], true);

        if ($settled) {
            $this->info('Đối soát COD: OK');
        }

        return self::SUCCESS;
    }

    private function actAs(User $user, callable $callback): void
    {
        $previous = Auth::user();
        Auth::setUser($user);

        try {
            $callback();
        } finally {
            if ($previous) {
                Auth::setUser($previous);
            } else {
                Auth::logout();
            }
        }
    }

    /** @param  array<string, mixed>  $data */
    private function salesRequest(User $user, string $uri, string $method, array $data = []): Request
    {
        $request = Request::create($uri, $method, $data);
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}
