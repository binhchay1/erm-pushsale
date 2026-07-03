<?php

namespace Database\Seeders;

use App\Enums\DeliveryStatus;
use App\Enums\LeadIngestionStatus;
use App\Models\LeadIngestion;
use App\Models\Order;
use App\Models\ShippingWebhookEvent;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Seeder;

/**
 * Thông báo mẫu cho từng vai trò, lấy từ chính dữ liệu đã seed
 * để bấm vào thông báo là ra đúng màn hình liên quan.
 */
class DemoNotificationSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()
            ->whereIn('email', [
                'admin@saleops.local',
                'sales@saleops.local',
                'marketing@saleops.local',
                'warehouse@saleops.local',
                'allocator@saleops.local',
                'accounting@saleops.local',
            ])
            ->get()
            ->keyBy('email');

        $pendingLead = LeadIngestion::query()->where('status', LeadIngestionStatus::Pending)->latest()->first();
        $mismatch = ShippingWebhookEvent::query()->where('is_cod_mismatch', true)->latest('received_at')->first();
        $returnedOrder = Order::query()->where('delivery_status', DeliveryStatus::Returned->value)->latest('closed_at')->first();
        $paidOrder = Order::query()->where('delivery_status', DeliveryStatus::Paid->value)->latest('closed_at')->first();

        $this->notify($users->get('admin@saleops.local'), [
            ['lead', 'Lead mới đang chờ chia số', $pendingLead ? ($pendingLead->customer_name.' · '.($pendingLead->customer_phone ?? 'thiếu SĐT')) : 'Có lead chờ xử lý', '/admin/leads'],
            ['system', 'Chiến dịch Landing chờ duyệt', 'Serum Vitamin C — Zalo Ads đang chờ phê duyệt', '/admin/landing/approvals', true],
            ['order', 'Doanh số hôm nay đã cập nhật', 'Xem tổng quan điều hành mới nhất', '/admin/dashboard', true],
        ]);

        $sale = $users->get('sales@saleops.local');
        $latestAssigned = $sale
            ? Order::query()->where('sale_user_id', $sale->id)->latest('assigned_at')->first()
            : null;

        $this->notify($sale, [
            ['lead', 'Bạn được gán khách mới', $latestAssigned ? ($latestAssigned->customer_name.' · '.$latestAssigned->customer_phone) : 'Vào màn tác nghiệp để xem', '/sales/workspace'],
        ]);

        $this->notify($users->get('marketing@saleops.local'), [
            ['system', 'Chiến dịch Facebook đang chạy tốt', 'Gối mây đan — Facebook Ads tiếp tục có đơn mới', '/marketing/campaigns'],
        ]);

        $this->notify($users->get('warehouse@saleops.local'), [
            ['order', 'Có đơn chờ tạo vận đơn', 'Vào màn xuất kho & vận đơn để xử lý', '/warehouse/operations'],
            ['order', 'Hàng hoàn cần nhập kho', $returnedOrder ? 'Đơn '.$returnedOrder->order_code.' — '.$returnedOrder->return_reason : 'Kiểm tra tab đơn hoàn', '/warehouse/operations', true],
        ]);

        $this->notify($users->get('allocator@saleops.local'), [
            ['lead', 'Lead chờ chia số thủ công', $pendingLead ? ($pendingLead->customer_name.' từ '.$pendingLead->platform) : 'Có lead trong hàng chờ', '/allocator/dashboard'],
        ]);

        $this->notify($users->get('accounting@saleops.local'), [
            ['shipping', 'Phát hiện lệch COD', $mismatch ? ('Vận đơn '.$mismatch->tracking_number.' — hãng báo '.number_format((float) $mismatch->partner_cod).'đ') : 'Kiểm tra màn đối soát', '/accounting/dashboard'],
            ['order', 'Đơn đã thu tiền COD', $paidOrder ? 'Đơn '.$paidOrder->order_code.' đã đối soát xong' : 'Xem danh sách đơn đã thanh toán', '/accounting/dashboard', true],
        ]);

        $this->command?->info('Đã tạo thông báo mẫu cho 6 tài khoản chính.');
    }

    /** @param  list<array{0: string, 1: string, 2: string, 3: string, 4?: bool}>  $items */
    private function notify(?User $user, array $items): void
    {
        if (! $user) {
            return;
        }

        foreach ($items as $item) {
            UserNotification::query()->create([
                'user_id' => $user->id,
                'type' => $item[0],
                'title' => $item[1],
                'message' => $item[2],
                'url' => $item[3],
                'read_at' => ($item[4] ?? false) ? now()->subHours(3) : null,
            ]);
        }
    }
}
