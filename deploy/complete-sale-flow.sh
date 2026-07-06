#!/usr/bin/env bash
# Hoàn tất telesale chốt đơn + webhook ship cho đơn demo (sau khi lead đã processed).
set -euo pipefail
PHONE="${1:?phone required}"
cd /var/www/erm-pushsale

php artisan tinker --execute="
\$phone = '$PHONE';
\$order = App\Models\Order::query()->where('customer_phone', \$phone)->latest('id')->first();
if (!\$order) { echo 'NO_ORDER'; exit(1); }
\$sale = App\Models\User::query()->find(\$order->sale_user_id);
\$product = App\Models\Product::query()->find(\$order->items()->first()?->product_id) ?? App\Models\Product::query()->first();
\$warehouse = App\Models\Warehouse::query()->first();
if (!\$sale) { echo 'NO_SALE'; exit(1); }

Auth::setUser(\$sale);

\$callReq = Illuminate\Http\Request::create('/sales/orders/'.\$order->id.'/call', 'POST');
\$callReq->setUserResolver(fn () => \$sale);
app(App\Http\Controllers\Sales\SaleOperationCallController::class)->store(
    \$callReq,
    \$order->fresh(),
    app(App\Services\Operations\SaleOperationStatusService::class)
);

\$req = Illuminate\Http\Request::create('/sales/orders/'.\$order->id.'/operation-status', 'POST', [
    'operation_result' => 'sent_quote',
    'note' => 'Audit flow — báo giá',
]);
\$req->setUserResolver(fn () => \$sale);
app(App\Http\Controllers\Sales\SaleOperationStatusController::class)->update(
    \$req, \$order->fresh(), app(App\Services\Operations\SaleOperationStatusService::class)
);

\$closeReq = Illuminate\Http\Request::create('/sales/orders/'.\$order->id.'/close', 'POST', [
    'shipping_provider' => 'ghtk',
    'warehouse_id' => \$warehouse?->id,
    'amount_to_collect' => (int) (\$product?->unit_price ?? 500000),
    'shipping_address' => '88 Nguyen Hue, Q1, HCM',
    'confirm_insufficient_stock' => false,
]);
\$closeReq->setUserResolver(fn () => \$sale);
app(App\Http\Controllers\Sales\OrderClosingController::class)->store(
    \$closeReq, \$order->fresh(), app(App\Services\Orders\OrderClosingService::class)
);

\$order->refresh();
\$tracking = \$order->tracking_number ?: ('AUDIT'.strtoupper(Illuminate\Support\Str::random(6)));
if (!\$order->tracking_number) {
    \$order->update(['tracking_number' => \$tracking, 'shipping_provider' => 'ghtk']);
}

\$ship = app(App\Services\Shipping\ShippingWebhookService::class);
\$ship->process('ghtk', [
    'label' => \$tracking,
    'order_code' => \$order->order_code,
    'status_id' => 6,
    'status_text' => 'Đã giao hàng',
    'cod' => \$order->amount_to_collect,
]);
\$ship->process('ghtk', [
    'label' => \$tracking,
    'order_code' => \$order->order_code,
    'status_text' => 'paid',
    'cod' => \$order->amount_to_collect,
]);

\$order->refresh();
echo 'order='.\$order->order_code.' delivery='.\$order->delivery_status.' recon='.\$order->reconciliation_status.PHP_EOL;
"

for i in 1 2 3; do
  sudo -u www-data php artisan queue:work database --once --quiet || true
done
