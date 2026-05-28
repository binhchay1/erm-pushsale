<?php

namespace Database\Seeders;

use App\Enums\DeliveryStatus;
use App\Enums\OperationStage;
use App\Enums\TeamType;
use App\Enums\UserRole;
use App\Models\FailedPartnerOrder;
use App\Models\MarketingSource;
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
        $sales = User::query()->where('role', UserRole::Sales)->get();
        $marketingUsers = User::query()->where('role', UserRole::Marketing)->get();
        $warehouseUsers = User::query()->where('role', UserRole::Warehouse)->get();
        $allocatorUsers = User::query()->where('role', UserRole::Allocator)->get();
        $accountingUsers = User::query()->where('role', UserRole::Accounting)->get();

        $rootTeam = Team::query()->create([
            'name' => 'Khối vận hành',
            'type' => TeamType::Sale,
            'leader_user_id' => $admin?->id,
        ]);

        $saleTeam = Team::query()->create([
            'name' => 'Nhóm Sale A',
            'type' => TeamType::Sale,
            'leader_user_id' => $admin?->id,
            'parent_id' => $rootTeam->id,
        ]);

        $mktTeam = Team::query()->create([
            'name' => 'Nhóm Marketing',
            'type' => TeamType::Marketing,
            'leader_user_id' => $admin?->id,
            'parent_id' => $rootTeam->id,
        ]);

        $warehouseTeam = Team::query()->create([
            'name' => 'Nhóm Kho',
            'type' => TeamType::Warehouse,
            'leader_user_id' => $admin?->id,
            'parent_id' => $rootTeam->id,
        ]);

        $allocatorTeam = Team::query()->create([
            'name' => 'Nhóm Chia số',
            'type' => TeamType::Allocator,
            'leader_user_id' => $admin?->id,
            'parent_id' => $rootTeam->id,
        ]);

        $accountingTeam = Team::query()->create([
            'name' => 'Nhóm Kế toán',
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

        $parentProduct = Product::query()->create([
            'name' => 'Gối mây đan',
            'sku' => 'SP-PARENT-01',
            'unit_price' => 299_000,
        ]);

        $product = Product::query()->create([
            'parent_id' => $parentProduct->id,
            'name' => 'Gối mây đan (SP292627)',
            'sku' => 'SP292627',
            'unit_price' => 159_000,
        ]);

        $camera = Product::query()->create([
            'name' => 'Camera mini NK',
            'sku' => 'CAM-MINI',
            'unit_price' => 890_000,
        ]);

        $warehouse = Warehouse::query()->create([
            'name' => 'Kho Hòa Bình',
            'code' => 'HB',
        ]);

        $sourceParent = MarketingSource::query()->create([
            'name' => 'Hải - camera mini nhật bản',
            'product_id' => $camera->id,
            'ad_channel' => 'Facebook ads',
            'utm_source' => 'facebook',
            'budget' => 5_000_000,
            'interactions' => 1200,
            'contacts' => 180,
        ]);

        MarketingSource::query()->create([
            'parent_id' => $sourceParent->id,
            'name' => $sourceParent->name,
            'product_id' => $camera->id,
            'ad_channel' => 'Youtube',
            'utm_source' => 'youtube',
            'utm_campaign' => 'cam-mini-q2',
            'budget' => 2_000_000,
            'interactions' => 400,
            'contacts' => 60,
        ]);

        $source2 = MarketingSource::query()->create([
            'name' => 'Ngọc Huyền - GG - Bột diệt cỏ',
            'product_id' => $product->id,
            'ad_channel' => 'Google',
            'utm_source' => 'google',
            'budget' => 3_500_000,
            'interactions' => 800,
            'contacts' => 95,
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
        $i = 0;

        foreach ($sales as $saleUser) {
            for ($n = 0; $n < 12; $n++) {
                $i++;
                $status = $statuses[$n % count($statuses)];
                $stage = $stages[$n % count($stages)];
                $source = $n % 2 === 0 ? $sourceParent : $source2;
                $qty = random_int(1, 3);
                $unitPrice = $product->unit_price;
                $subtotal = $qty * $unitPrice;

                $order = Order::query()->create([
                    'order_code' => 'PS'.str_pad((string) (1_800_000 + $i), 11, '0', STR_PAD_LEFT),
                    'sale_user_id' => $saleUser->id,
                    'marketer_user_id' => $admin?->id,
                    'team_id' => $saleTeam->id,
                    'marketing_source_id' => $source->id,
                    'warehouse_id' => $warehouse->id,
                    'product_id' => $product->id,
                    'customer_name' => 'Khách hàng '.$i,
                    'customer_phone' => '09'.random_int(10000000, 99999999),
                    'phone_carrier' => 'VIETTEL',
                    'customer_note' => 'Ghi chú đơn mẫu #'.$i,
                    'shipping_address' => 'Hà Nội — địa chỉ demo '.$i,
                    'data_arrived_at' => now()->subDays(random_int(0, 7)),
                    'assigned_at' => now()->subDays(random_int(0, 6)),
                    'closed_at' => now()->subDays(random_int(0, 5)),
                    'operation_stage' => $stage->value,
                    'delivery_status' => $status->value,
                    'carrier_name' => 'Viettel Post(COD)',
                    'tracking_number' => 'VT'.random_int(100000, 999999),
                    'is_returning_customer' => $n % 3 === 0,
                    'subtotal' => $subtotal,
                    'discount' => (int) ($subtotal * 0.05),
                    'vat' => 0,
                    'shipping_fee_collected' => 30_000,
                    'total' => $subtotal + 30_000,
                    'deposit' => $n % 4 === 0 ? 100_000 : 0,
                    'amount_to_collect' => $subtotal + 30_000,
                    'contact_count' => 1,
                    'cod_fee' => 15_000,
                    'cod_support' => 5_000,
                ]);

                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                ]);
            }
        }

        WarehouseInventory::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'stock_quantity' => 36,
            'pending_sales_quantity' => 12,
            'location_code' => 'A-01',
        ]);

        WarehouseInventory::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $camera->id,
            'stock_quantity' => 7,
            'pending_sales_quantity' => -2,
            'location_code' => 'B-12',
        ]);

        FailedPartnerOrder::query()->create([
            'platform' => 'TikTok',
            'warehouse_id' => $warehouse->id,
            'shop_name' => 'Shop demo',
            'partner_order_id' => 'TT-'.random_int(10000, 99999),
            'error_description' => 'Mã đơn không khớp kho',
        ]);
    }
}
