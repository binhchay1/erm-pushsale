<?php

namespace Database\Seeders;

use App\Enums\ClosingStatus;
use App\Enums\DeliveryStatus;
use App\Enums\OperationResult;
use App\Enums\OperationStage;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class SaleOperationSeeder extends Seeder
{
    public function run(): void
    {
        $saleUser = User::query()->where('email', 'sales@saleops.local')->first();
        $marketer = User::query()->where('email', 'marketing@saleops.local')->first();
        $product = Product::query()->where('sku', 'SP292627')->first();
        $camera = Product::query()->where('sku', 'CAM-MINI')->first();
        $warehouse = Warehouse::query()->where('code', 'HB')->first();
        $saleTeam = Team::query()->where('name', 'Nhóm Sale A')->first();
        $source = MarketingSource::query()->where('name', 'Hải - camera mini nhật bản')->first()
            ?? MarketingSource::query()->first();

        if (! $saleUser || ! $product || ! $warehouse || ! $saleTeam || ! $source) {
            return;
        }

        $scenarios = [
            [
                'code' => 'PS-OPS-00001',
                'stage' => OperationStage::NewCustomer,
                'result' => OperationResult::NoContact,
                'note' => 'Lead mới từ Facebook — khách để lại SĐT qua form landing.',
                'customer' => 'Trần Minh Anh',
                'phone' => '0912345678',
                'carrier' => 'VIETTEL',
                'daysAgo' => 0,
                'closed' => false,
                'contact' => 0,
                'product' => $camera,
            ],
            [
                'code' => 'PS-OPS-00002',
                'stage' => OperationStage::Call2,
                'result' => OperationResult::NoAnswer1,
                'note' => 'Gọi lần 1 không nghe — hẹn gọi lại 14:00 chiều nay.',
                'customer' => 'Lê Hoàng Nam',
                'phone' => '0988111222',
                'carrier' => 'VINAPHONE',
                'daysAgo' => 0,
                'closed' => false,
                'contact' => 1,
                'product' => $product,
            ],
            [
                'code' => 'PS-OPS-00003',
                'stage' => OperationStage::Call3,
                'result' => OperationResult::CallbackScheduled,
                'note' => 'Khách đang lái xe, nhờ gọi lại sau 2 tiếng.',
                'customer' => 'Phạm Thu Hà',
                'phone' => '0977665544',
                'carrier' => 'MOBIFONE',
                'daysAgo' => 1,
                'closed' => false,
                'contact' => 2,
                'product' => $product,
            ],
            [
                'code' => 'PS-OPS-00004',
                'stage' => OperationStage::Call4,
                'result' => OperationResult::SentQuote,
                'note' => 'Đã gửi combo 2 gối giảm 10%. Chờ phản hồi trên Zalo.',
                'customer' => 'Nguyễn Văn Bình',
                'phone' => '0909123456',
                'carrier' => 'VIETTEL',
                'daysAgo' => 1,
                'closed' => false,
                'contact' => 3,
                'product' => $product,
            ],
            [
                'code' => 'PS-OPS-00005',
                'stage' => OperationStage::Call5,
                'result' => OperationResult::Considering,
                'note' => 'Khách hỏi thêm phí ship về Đà Nẵng, đã báo 35k.',
                'customer' => 'Hoàng Thị Lan',
                'phone' => '0934567890',
                'carrier' => 'VIETTEL',
                'daysAgo' => 2,
                'closed' => false,
                'contact' => 4,
                'product' => $camera,
            ],
            [
                'code' => 'PS-OPS-00006',
                'stage' => OperationStage::Call6,
                'result' => OperationResult::ReadyToClose,
                'note' => 'Khách xác nhận địa chỉ 123 Nguyễn Trãi, Q.5 — chờ chốt.',
                'customer' => 'Đỗ Quốc Huy',
                'phone' => '0966888999',
                'carrier' => 'VIETTEL',
                'daysAgo' => 0,
                'closed' => false,
                'contact' => 5,
                'product' => $product,
            ],
            [
                'code' => 'PS-OPS-00007',
                'stage' => OperationStage::Care1,
                'result' => OperationResult::ClosedSuccess,
                'note' => 'Đơn COD 318k, khách muốn nhận tối thứ 7.',
                'customer' => 'Võ Thị Mai',
                'phone' => '0945123789',
                'carrier' => 'MOBIFONE',
                'daysAgo' => 0,
                'closed' => true,
                'contact' => 6,
                'product' => $product,
            ],
            [
                'code' => 'PS-OPS-00008',
                'stage' => OperationStage::Care2,
                'result' => OperationResult::ClosedSuccess,
                'note' => 'Khách nhận hàng OK, hỏi thêm sản phẩm liên quan.',
                'customer' => 'Bùi Văn Tài',
                'phone' => '0922334455',
                'carrier' => 'VINAPHONE',
                'daysAgo' => 3,
                'closed' => true,
                'contact' => 2,
                'product' => $product,
            ],
            [
                'code' => 'PS-OPS-00009',
                'stage' => OperationStage::Care3,
                'result' => OperationResult::ClosedSuccess,
                'note' => 'Khách cũ mua thêm 1 camera mini sau CS lần 3.',
                'customer' => 'Ngô Thị Hồng',
                'phone' => '0918776655',
                'carrier' => 'VIETTEL',
                'daysAgo' => 5,
                'closed' => true,
                'contact' => 3,
                'product' => $camera,
                'returning' => true,
            ],
            [
                'code' => 'PS-OPS-00010',
                'stage' => OperationStage::Skipped,
                'result' => OperationResult::PriceRejected,
                'note' => 'Khách so sánh giá sàn TMDT, không chốt.',
                'customer' => 'Trịnh Văn Đức',
                'phone' => '0900112233',
                'carrier' => 'VIETTEL',
                'daysAgo' => 2,
                'closed' => false,
                'contact' => 4,
                'product' => $product,
            ],
            [
                'code' => 'PS-OPS-00011',
                'stage' => OperationStage::NoOperation,
                'result' => OperationResult::WrongNumber,
                'note' => 'SĐT không tồn tại — cần marketing xác minh lại lead.',
                'customer' => 'Khách không xác định',
                'phone' => '0999000111',
                'carrier' => null,
                'daysAgo' => 1,
                'closed' => false,
                'contact' => 0,
                'product' => $product,
            ],
            [
                'code' => 'PS-OPS-00012',
                'stage' => OperationStage::NewCustomer,
                'result' => OperationResult::NoContact,
                'note' => 'Khách đã mua tháng trước, inbox hỏi mua thêm quà tặng.',
                'customer' => 'Phan Minh Tuấn',
                'phone' => '0933445566',
                'carrier' => 'VIETTEL',
                'daysAgo' => 0,
                'closed' => false,
                'contact' => 0,
                'product' => $product,
                'returning' => true,
            ],
        ];

        foreach ($scenarios as $scenario) {
            $pickedProduct = $scenario['product'] ?? $product;
            $qty = 1;
            $unitPrice = $pickedProduct->unit_price;
            $subtotal = $qty * $unitPrice;
            $dataArrived = now()->subDays($scenario['daysAgo'])->setTime(9, 30);
            $assignedAt = $dataArrived->copy()->addHours(2);
            $closedAt = $scenario['closed'] ? $assignedAt->copy()->addHours(4) : null;
            $deliveryStatus = $scenario['closed']
                ? DeliveryStatus::WaitingWaybill->value
                : DeliveryStatus::DeliverNow->value;

            $order = Order::query()->updateOrCreate(['order_code' => $scenario['code']], [
                'sale_user_id' => $saleUser->id,
                'marketer_user_id' => $marketer?->id,
                'team_id' => $saleTeam->id,
                'marketing_source_id' => $source->id,
                'warehouse_id' => $warehouse->id,
                'product_id' => $pickedProduct->id,
                'customer_name' => $scenario['customer'],
                'customer_phone' => $scenario['phone'],
                'phone_carrier' => $scenario['carrier'],
                'customer_note' => $scenario['note'],
                'shipping_address' => 'Địa chỉ demo — '.$scenario['customer'],
                'data_arrived_at' => $dataArrived,
                'assigned_at' => $assignedAt,
                'closed_at' => $closedAt,
                'desired_delivery_at' => now()->addDays(2)->toDateString(),
                'operation_stage' => $scenario['stage']->value,
                'operation_result' => $scenario['result']->value,
                'closing_status' => $scenario['closed']
                    ? ClosingStatus::Closed->value
                    : ($scenario['stage'] === OperationStage::Skipped ? ClosingStatus::Cancelled->value : ClosingStatus::Open->value),
                'delivery_status' => $deliveryStatus,
                'is_returning_customer' => $scenario['returning'] ?? false,
                'subtotal' => $subtotal,
                'discount' => 0,
                'vat' => 0,
                'shipping_fee_collected' => 30_000,
                'total' => $subtotal + 30_000,
                'deposit' => 0,
                'amount_to_collect' => $subtotal + 30_000,
                'contact_count' => $scenario['contact'],
                'cod_fee' => 15_000,
                'cod_support' => 5_000,
            ]);

            OrderItem::query()->updateOrCreate([
                'order_id' => $order->id,
                'product_id' => $pickedProduct->id,
            ], [
                'product_name' => $pickedProduct->name,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
            ]);
        }
    }
}
