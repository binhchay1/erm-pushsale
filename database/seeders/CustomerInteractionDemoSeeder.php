<?php

namespace Database\Seeders;

use App\Enums\OperationResult;
use App\Enums\OperationStage;
use App\Enums\UserRole;
use App\Models\CustomerInternalMessage;
use App\Models\Order;
use App\Models\OrderOperationHistory;
use App\Models\User;
use App\Services\CustomerInteractions\CustomerIdentity;
use App\Services\Tenant\CompanyProvisioningService;
use App\Support\TenantManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomerInteractionDemoSeeder extends Seeder
{
    public function run(): void
    {
        $company = CompanyProvisioningService::internalCompany();

        app(TenantManager::class)->forCompany($company->id, function (): void {
            $orders = Order::query()
                ->with('saleUser')
                ->whereNotNull('customer_phone')
                ->latest('data_arrived_at')
                ->limit(10)
                ->get();

            if ($orders->isEmpty()) {
                $this->command?->warn('Chưa có đơn hàng. Hãy chạy SalesPipelineSeeder trước.');

                return;
            }

            $admin = User::query()->where('role', UserRole::Admin)->first();
            $warehouse = User::query()->where('role', UserRole::Warehouse)->first();

            DB::transaction(function () use ($orders, $admin, $warehouse): void {
                foreach ($orders as $offset => $order) {
                    $sale = $order->saleUser
                        ?? User::query()->where('role', UserRole::Sales)->first()
                        ?? $admin;

                    if (! $sale) {
                        continue;
                    }

                    OrderOperationHistory::query()
                        ->where('order_id', $order->id)
                        ->where('note', 'like', 'DEMO:%')
                        ->delete();

                    CustomerInternalMessage::query()
                        ->where('customer_phone', CustomerIdentity::phoneKey($order))
                        ->where('message', 'like', '[DEMO]%')
                        ->delete();

                    $base = now()->subHours(20 + $offset);

                    $historyRows = [
                        [
                            'action' => OrderOperationHistory::ACTION_CALL,
                            'before' => OperationStage::NewCustomer->value,
                            'after' => OperationStage::NewCustomer->value,
                            'result' => OperationResult::NoContact->value,
                            'note' => 'DEMO: Sale thực hiện cuộc gọi đầu tiên cho khách.',
                            'at' => $base,
                        ],
                        [
                            'action' => OrderOperationHistory::ACTION_STATUS_UPDATED,
                            'before' => OperationStage::NewCustomer->value,
                            'after' => OperationStage::Call2->value,
                            'result' => OperationResult::CallbackScheduled->value,
                            'note' => 'DEMO: Khách bận và hẹn gọi lại vào buổi chiều.',
                            'at' => $base->copy()->addMinutes(25),
                        ],
                        [
                            'action' => OrderOperationHistory::ACTION_ORDER_UPDATED,
                            'before' => OperationStage::Call2->value,
                            'after' => $order->operation_stage ?: OperationStage::Call2->value,
                            'result' => $order->operation_result ?: OperationResult::Considering->value,
                            'note' => 'DEMO: Đã xác nhận địa chỉ, sản phẩm và ghi chú giao hàng.',
                            'at' => $base->copy()->addHours(2),
                        ],
                    ];

                    foreach ($historyRows as $history) {
                        OrderOperationHistory::query()->create([
                            'company_id' => $order->company_id,
                            'order_id' => $order->id,
                            'actor_user_id' => $sale->id,
                            'actor_name' => $sale->name,
                            'actor_role' => $sale->role?->value,
                            'action' => $history['action'],
                            'operation_stage_before' => $history['before'],
                            'operation_stage_after' => $history['after'],
                            'operation_result' => $history['result'],
                            'next_operation_at' => $history['action'] === OrderOperationHistory::ACTION_STATUS_UPDATED
                                ? $base->copy()->addDay()
                                : null,
                            'note' => $history['note'],
                            'metadata' => ['demo' => true, 'contact_count' => 1],
                            'created_at' => $history['at'],
                        ]);
                    }

                    $authors = collect([$sale, $admin, $warehouse])->filter()->unique('id')->values();
                    $contents = [
                        '[DEMO] Sale đã gọi và khách hẹn trao đổi lại vào chiều nay.',
                        '[DEMO] Đã kiểm tra thông tin khách hàng, tiếp tục bám theo lịch tác nghiệp.',
                        '[DEMO] Kho đã xem địa chỉ giao hàng và sẵn sàng xử lý khi đơn được chốt.',
                    ];

                    foreach ($authors as $index => $author) {
                        $message = new CustomerInternalMessage([
                            'company_id' => $order->company_id,
                            'order_id' => $order->id,
                            'author_user_id' => $author->id,
                            'author_name' => $author->name,
                            'author_role' => $author->role?->value,
                            'customer_phone' => CustomerIdentity::phoneKey($order),
                            'message' => $contents[$index] ?? '[DEMO] Đã cập nhật thông tin nội bộ.',
                        ]);
                        $message->created_at = $base->copy()->addHours(3)->addMinutes($index * 12);
                        $message->updated_at = $message->created_at;
                        $message->save();
                    }
                }
            });

            $this->command?->info("Đã tạo dữ liệu demo tương tác cho {$orders->count()} đơn hàng.");
        });
    }
}
