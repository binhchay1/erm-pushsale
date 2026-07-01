<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Leads\LeadOrderFactory;
use Illuminate\Console\Command;

class SyncOrderTotalsCommand extends Command
{
    protected $signature = 'orders:sync-totals';

    protected $description = 'Tính lại subtotal/total từ order_items (sửa đơn thiếu doanh thu trên báo cáo)';

    public function handle(LeadOrderFactory $factory): int
    {
        $updated = 0;

        Order::query()->with('items')->chunkById(200, function ($orders) use ($factory, &$updated) {
            foreach ($orders as $order) {
                if ($order->items->isEmpty()) {
                    continue;
                }

                $before = (int) $order->total;
                $factory->syncTotals($order);
                if ((int) $order->fresh()->total !== $before) {
                    $updated++;
                }
            }
        });

        $this->info("Đã đồng bộ {$updated} đơn.");

        return self::SUCCESS;
    }
}
