<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Pushsale\WarehouseIncidentReport;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class WarehouseDeliveryHandoverSeeder extends Seeder
{
    public function run(): void
    {
        $warehouseUsers = User::query()->where('role', User::ROLE_WAREHOUSE)->orderBy('id')->get();
        $actor = $warehouseUsers->first() ?: User::query()->orderBy('id')->first();
        if (! $actor) {
            return;
        }

        $providers = collect(config('shipping_partners.providers', []))->keys()->values();
        if ($providers->isEmpty()) {
            $providers = collect(['manual', 'ghn', 'viettel_post', 'ghtk']);
        }

        $statuses = ['updating', 'closed', 'updating', 'closed', 'closed', 'updating'];
        $baseDate = Carbon::now()->subDays(5);
        $ordersByProvider = Order::query()
            ->selectRaw('COALESCE(shipping_method, ?) as provider, COUNT(*) as orders_count', ['manual'])
            ->groupBy('provider')
            ->pluck('orders_count', 'provider');

        foreach ($providers->take(8)->values() as $index => $provider) {
            $provider = (string) $provider;
            $orderCount = (int) ($ordersByProvider[$provider] ?? (4 + ($index * 3)));
            $productCount = max($orderCount, $orderCount * (2 + ($index % 3)));

            WarehouseIncidentReport::query()->updateOrCreate(
                ['name' => 'BIÊN BẢN BÀN GIAO VẬN ĐƠN '.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)],
                [
                    'manager_user_id' => $warehouseUsers->get($index % max(1, $warehouseUsers->count()))?->id ?? $actor->id,
                    'document_date' => $baseDate->copy()->addDays($index)->toDateString(),
                    'carrier' => $provider,
                    'sender_name' => $actor->name ?: $actor->email,
                    'receiver_name' => match ($provider) {
                        'manual' => 'Kho nhận thủ công',
                        'ghn' => 'Điều phối GHN',
                        'ghtk' => 'Điều phối GHTK',
                        'viettel_post' => 'Điều phối Viettel Post',
                        default => 'Nhân sự nhận bàn giao '.strtoupper(str_replace('_', ' ', $provider)),
                    },
                    'order_count' => $orderCount,
                    'product_count' => $productCount,
                    'status' => $statuses[$index % count($statuses)],
                    'note' => 'Biên bản demo sinh từ luồng đơn/kho để kiểm thử màn 5.4.',
                    'created_by_user_id' => $actor->id,
                    'updated_by_user_id' => $actor->id,
                ]
            );
        }

        $this->command?->info('Đã tạo dữ liệu demo biên bản bàn giao vận đơn.');
    }
}
