<?php

namespace Database\Seeders;

use App\Enums\DeliveryStatus;
use App\Enums\OperationStage;
use App\Enums\TeamType;
use App\Enums\UserRole;
use App\Models\FailedPartnerOrder;
use App\Models\MarketingSource;
use App\Models\UserNotification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use Illuminate\Database\Seeder;

class SaleOpsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@saleops.local')->first();

        // Nhân sự demo cho bảng xếp hạng (đủ để thấy top 10 + top 50).
        $this->ensureDemoStaff(UserRole::Sales, 'sale', 'Telesale', 55);
        $this->ensureDemoStaff(UserRole::Marketing, 'mkt', 'Marketing', 55);

        $sales = User::query()->where('role', UserRole::Sales)->get();
        $marketingUsers = User::query()->where('role', UserRole::Marketing)->get();
        $warehouseUsers = User::query()->where('role', UserRole::Warehouse)->get();
        $allocatorUsers = User::query()->where('role', UserRole::Allocator)->get();
        $accountingUsers = User::query()->where('role', UserRole::Accounting)->get();

        $rootTeam = Team::query()->firstOrCreate([
            'name' => 'Khối vận hành',
        ], [
            'type' => TeamType::Sale,
            'leader_user_id' => $admin?->id,
        ]);

        $saleTeam = Team::query()->firstOrCreate([
            'name' => 'Nhóm Sale A',
        ], [
            'type' => TeamType::Sale,
            'leader_user_id' => $admin?->id,
            'parent_id' => $rootTeam->id,
        ]);

        $mktTeam = Team::query()->firstOrCreate([
            'name' => 'Nhóm Marketing',
        ], [
            'type' => TeamType::Marketing,
            'leader_user_id' => $admin?->id,
            'parent_id' => $rootTeam->id,
        ]);

        $warehouseTeam = Team::query()->firstOrCreate([
            'name' => 'Nhóm Kho',
        ], [
            'type' => TeamType::Warehouse,
            'leader_user_id' => $admin?->id,
            'parent_id' => $rootTeam->id,
        ]);

        $allocatorTeam = Team::query()->firstOrCreate([
            'name' => 'Nhóm Chia số',
        ], [
            'type' => TeamType::Allocator,
            'leader_user_id' => $admin?->id,
            'parent_id' => $rootTeam->id,
        ]);

        $accountingTeam = Team::query()->firstOrCreate([
            'name' => 'Nhóm Kế toán',
        ], [
            'type' => TeamType::Accounting,
            'leader_user_id' => $admin?->id,
            'parent_id' => $rootTeam->id,
        ]);

        $sales->each(fn (User $u) => $u->update([
            'team_id' => $saleTeam->id,
            'manager_user_id' => $admin?->id,
        ]));
        $marketingUsers->each(fn (User $u) => $u->update([
            'team_id' => $mktTeam->id,
            'manager_user_id' => $admin?->id,
        ]));
        $warehouseUsers->each(fn (User $u) => $u->update([
            'team_id' => $warehouseTeam->id,
            'manager_user_id' => $admin?->id,
        ]));
        $allocatorUsers->each(fn (User $u) => $u->update([
            'team_id' => $allocatorTeam->id,
            'manager_user_id' => $admin?->id,
        ]));
        $accountingUsers->each(fn (User $u) => $u->update([
            'team_id' => $accountingTeam->id,
            'manager_user_id' => $admin?->id,
        ]));

        $parentProduct = Product::query()->firstOrCreate([
            'sku' => 'SP-PARENT-01',
        ], [
            'name' => 'Gối mây đan',
            'unit_price' => 299_000,
        ]);

        $product = Product::query()->firstOrCreate([
            'sku' => 'SP292627',
        ], [
            'parent_id' => $parentProduct->id,
            'name' => 'Gối mây đan (SP292627)',
            'unit_price' => 159_000,
        ]);

        $camera = Product::query()->firstOrCreate([
            'sku' => 'CAM-MINI',
        ], [
            'name' => 'Camera mini NK',
            'unit_price' => 890_000,
        ]);

        $warehouse = Warehouse::query()->firstOrCreate([
            'code' => 'HB',
        ], [
            'name' => 'Kho Hòa Bình',
            'phone' => '0988111222',
            'address' => 'KCN Hòa Bình, Hà Nội',
            'manager_user_id' => $warehouseUsers->first()?->id ?? $admin?->id,
            'vtp_code' => 'VTP-HB-01',
        ]);

        $mkt = $marketingUsers->values();

        $sourceParent = MarketingSource::query()->firstOrCreate([
            'name' => 'Hải - camera mini nhật bản',
        ], [
            'name' => 'Hải - camera mini nhật bản',
            'product_id' => $camera->id,
            'marketer_user_id' => $mkt->get(0)?->id ?? $admin?->id,
            'ad_channel' => 'facebook',
            'utm_source' => 'facebook',
            'utm_campaign' => 'cam-mini-fb',
            'budget' => 5_000_000,
            'interactions' => 1200,
            'contacts' => 180,
            'is_active' => true,
        ]);

        MarketingSource::query()->firstOrCreate([
            'parent_id' => $sourceParent->id,
            'name' => $sourceParent->name,
        ], [
            'product_id' => $camera->id,
            'marketer_user_id' => $mkt->get(1)?->id ?? $admin?->id,
            'ad_channel' => 'google',
            'utm_source' => 'youtube',
            'utm_campaign' => 'cam-mini-q2',
            'budget' => 2_000_000,
            'interactions' => 400,
            'contacts' => 60,
            'is_active' => true,
        ]);

        $source2 = MarketingSource::query()->firstOrCreate([
            'name' => 'Ngọc Huyền - GG - Bột diệt cỏ',
        ], [
            'product_id' => $product->id,
            'marketer_user_id' => $mkt->get(2)?->id ?? $admin?->id,
            'ad_channel' => 'google',
            'utm_source' => 'google',
            'utm_campaign' => 'bot-diet-co-gg',
            'budget' => 3_500_000,
            'interactions' => 800,
            'contacts' => 95,
            'is_active' => true,
        ]);

        $statuses = [
            DeliveryStatus::WaitingWaybill,
            DeliveryStatus::Delivering,
            DeliveryStatus::Delivered,
            DeliveryStatus::Paid,
            DeliveryStatus::Returned,
            DeliveryStatus::CancelWaybill,
        ];

        $stages = OperationStage::cases();
        $marketers = $marketingUsers->values();
        $marketerCount = $marketers->count();
        $i = 0;

        foreach ($sales->values() as $sIndex => $saleUser) {
            // Số đơn lệch nhau theo nhân sự để tạo thứ hạng rõ ràng (3..24 đơn).
            $orderCount = 3 + (($sIndex * 13 + 7) % 22);

            for ($n = 0; $n < $orderCount; $n++) {
                $i++;
                $status = $statuses[$i % count($statuses)];
                $stage = $stages[$i % count($stages)];
                $source = $i % 2 === 0 ? $sourceParent : $source2;

                // Dồn 1/3 đơn về nhóm marketer đầu để top 10 marketing nổi bật.
                if ($marketerCount > 0) {
                    $marketerIndex = $i % 3 === 0
                        ? $i % min(10, $marketerCount)
                        : ($i * 7) % $marketerCount;
                    $marketer = $marketers[$marketerIndex];
                } else {
                    $marketer = $admin;
                }

                $qty = 1 + ($i % 3);
                $unitPrice = $product->unit_price;
                $subtotal = $qty * $unitPrice;

                // Trải ngày chốt suốt 1 quý để filter tuần/tháng/quý đều có dữ liệu.
                $closedAt = now()->subDays(($i * 7) % 95);

                $orderCode = 'PS'.str_pad((string) (1_800_000 + $i), 11, '0', STR_PAD_LEFT);

                $order = Order::query()->firstOrCreate([
                    'order_code' => $orderCode,
                ], [
                    'sale_user_id' => $saleUser->id,
                    'marketer_user_id' => $marketer?->id,
                    'team_id' => $saleTeam->id,
                    'marketing_source_id' => $source->id,
                    'warehouse_id' => $warehouse->id,
                    'product_id' => $product->id,
                    'customer_name' => 'Khách hàng '.$i,
                    'customer_phone' => '09'.random_int(10000000, 99999999),
                    'phone_carrier' => 'VIETTEL',
                    'customer_note' => 'Ghi chú đơn mẫu #'.$i,
                    'shipping_address' => 'Hà Nội — địa chỉ demo '.$i,
                    'data_arrived_at' => $closedAt->copy()->subDays(2),
                    'assigned_at' => $closedAt->copy()->subDay(),
                    'closed_at' => $closedAt,
                    'operation_stage' => $stage->value,
                    'delivery_status' => $status->value,
                    'carrier_name' => 'Viettel Post(COD)',
                    'tracking_number' => 'VT'.random_int(100000, 999999),
                    'is_returning_customer' => $i % 3 === 0,
                    'subtotal' => $subtotal,
                    'discount' => (int) ($subtotal * 0.05),
                    'vat' => 0,
                    'shipping_fee_collected' => 30_000,
                    'total' => $subtotal + 30_000,
                    'deposit' => $i % 4 === 0 ? 100_000 : 0,
                    'amount_to_collect' => $subtotal + 30_000,
                    'contact_count' => 1,
                    'cod_fee' => 15_000,
                    'cod_support' => 5_000,
                ]);

                OrderItem::query()->firstOrCreate([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                ], [
                    'product_name' => $product->name,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                ]);
            }
        }

        WarehouseInventory::query()->updateOrCreate([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
        ], [
            'stock_quantity' => 36,
            'pending_sales_quantity' => 12,
            'location_code' => 'A-01',
        ]);

        WarehouseInventory::query()->updateOrCreate([
            'warehouse_id' => $warehouse->id,
            'product_id' => $camera->id,
        ], [
            'stock_quantity' => 7,
            'pending_sales_quantity' => -2,
            'location_code' => 'B-12',
        ]);

        FailedPartnerOrder::query()->firstOrCreate([
            'platform' => 'TikTok',
            'partner_order_id' => 'TT-DEMO-00001',
        ], [
            'warehouse_id' => $warehouse->id,
            'shop_name' => 'Shop demo',
            'error_description' => 'Mã đơn không khớp kho',
        ]);

        $this->seedNotifications($admin);
    }

    private function seedNotifications(?User $admin): void
    {
        if (! $admin) {
            return;
        }

        $samples = [
            ['lead', 'Lead mới từ landing', 'Nguyễn Văn A · 0987654321', '/admin/leads'],
            ['order', 'Đơn PS1800001 đã giao thành công', 'COD 920.000đ đã thu', '/admin/reports/business'],
            ['shipping', 'Lệch COD vận đơn VT123456', 'Đối tác báo 850.000đ / hệ thống 920.000đ', '/admin/shipping/reconciliation'],
            ['system', 'Chiến dịch "cam-mini-fb" đang chạy', 'Đã nhận 12 lead trong hôm nay', '/admin/marketing/campaigns'],
        ];

        foreach ($samples as $index => [$type, $title, $message, $url]) {
            UserNotification::query()->firstOrCreate(
                ['user_id' => $admin->id, 'title' => $title],
                [
                    'type' => $type,
                    'message' => $message,
                    'url' => $url,
                    'read_at' => $index >= 2 ? now() : null,
                ],
            );
        }
    }

    private function ensureDemoStaff(UserRole $role, string $prefix, string $label, int $count): void
    {
        for ($n = 1; $n <= $count; $n++) {
            $seq = str_pad((string) $n, 2, '0', STR_PAD_LEFT);

            User::query()->firstOrCreate(
                ['email' => "{$prefix}{$seq}@saleops.local"],
                ['name' => "{$label} {$seq}", 'password' => 'password', 'role' => $role],
            );
        }
    }
}
