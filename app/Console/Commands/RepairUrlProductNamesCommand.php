<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Leads\LeadOrderFactory;
use App\Support\LandingProductLabel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repair order lines where product_name is a landing page URL / tracking dump
 * (LadiPage field mapped to "products" incorrectly).
 */
class RepairUrlProductNamesCommand extends Command
{
    protected $signature = 'leads:repair-url-product-names
                            {--dry-run : Chỉ thống kê, không ghi DB}
                            {--force : Bỏ qua xác nhận}';

    protected $description = 'Sửa order_items.product_name là URL / tracking → placeholder + unit_price=0';

    public function handle(LeadOrderFactory $orders): int
    {
        $items = OrderItem::query()
            ->where(function ($query): void {
                $query->where('product_name', 'like', 'http%')
                    ->orWhere('product_name', 'like', '//%')
                    ->orWhere('product_name', 'like', 'www.%');
            })
            ->get(['id', 'order_id', 'product_name', 'unit_price', 'meta']);

        $extra = OrderItem::query()
            ->whereNotNull('product_name')
            ->where('product_name', '!=', '')
            ->where('product_name', 'not like', 'http%')
            ->limit(5000)
            ->get(['id', 'order_id', 'product_name', 'unit_price', 'meta'])
            ->filter(fn (OrderItem $item): bool => LandingProductLabel::looksLikeUrl((string) $item->product_name));

        $all = $items->concat($extra)->unique('id')->values();
        $this->info('Tìm thấy '.$all->count().' dòng order_items nghi URL làm product_name.');

        if ($all->isEmpty()) {
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            foreach ($all->take(10) as $item) {
                $this->line("#{$item->id} order={$item->order_id} price={$item->unit_price} name=".mb_substr((string) $item->product_name, 0, 80));
            }

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Sửa các dòng này thành "Sản phẩm (chưa map)" và unit_price=0?', true)) {
            $this->warn('Đã hủy.');

            return self::SUCCESS;
        }

        $updated = 0;
        $orderIds = [];
        DB::transaction(function () use ($all, &$updated, &$orderIds): void {
            foreach ($all as $item) {
                $hint = LandingProductLabel::urlMonitorHint((string) $item->product_name);
                $meta = is_array($item->meta) ? $item->meta : [];
                $meta['rejected_url_product'] = true;
                $meta['rejected_url_hint'] = $hint;
                $meta['raw_label'] = mb_substr((string) $item->product_name, 0, 500);

                $item->forceFill([
                    'product_name' => 'Sản phẩm (chưa map)',
                    'unit_price' => 0,
                    'meta' => $meta,
                ])->save();
                $updated++;
                $orderIds[] = (int) $item->order_id;
            }
        });

        foreach (array_unique($orderIds) as $orderId) {
            $order = Order::query()->with('items')->find($orderId);
            if ($order) {
                $orders->syncTotals($order);
            }
        }

        $this->info("Đã sửa {$updated} dòng trên ".count(array_unique($orderIds)).' đơn.');

        return self::SUCCESS;
    }
}
