#!/usr/bin/env bash
# Test webhook campaign cụ thể trên prod.
set -euo pipefail
cd /var/www/erm-pushsale

TOKEN="${1:-zfwihdf7fgvpp1buzztbzrodsy7hzaxx}"
PHONE="${2:-0911$(date +%H%M%S)}"
BASE="${APP_URL:-https://erm-pushsale.duckdns.org}"

echo "=== CAMPAIGN INFO ==="
php artisan tinker --execute="
\$c = App\Models\MarketingSource::query()->where('webhook_token', '$TOKEN')->first();
if (!\$c) { echo 'NOT_FOUND'; exit(1); }
echo 'id='.\$c->id.PHP_EOL;
echo 'name='.\$c->name.PHP_EOL;
echo 'approved='.(\$c->is_approved ? 'yes' : 'no').PHP_EOL;
echo 'active='.(\$c->is_active ? 'yes' : 'no').PHP_EOL;
echo 'js_tracking='.(\$c->js_tracking_enabled ? 'yes' : 'no').PHP_EOL;
echo 'contacts='.\$c->contacts.PHP_EOL;
"

echo ""
echo "=== WEBHOOK RECEIVE (phone=$PHONE) ==="
HTTP=$(curl -s -o /tmp/wh_recv.json -w '%{http_code}' -X POST \
  "$BASE/api/v1/landing/$TOKEN/receive" \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d "{\"submission_id\":\"ladipage-test-$(date +%s)\",\"name\":\"Chị Test Landing\",\"phone\":\"$PHONE\",\"address\":\"12 Nguyễn Trãi, Hà Nội\",\"combo\":\"Mua 2 Thỏi : 289k + Miễn Ship (Bán Chạy)\"}")
echo "HTTP $HTTP"
cat /tmp/wh_recv.json
echo ""

sudo -u www-data php artisan queue:wait-empty --queue=webhooks --timeout=60

echo ""
echo "=== SAU RECEIVE ==="
php artisan tinker --execute="
\$phone='$PHONE';
\$lead = App\Models\LeadIngestion::query()->where('customer_phone',\$phone)->latest('id')->first();
\$order = App\Models\Order::query()->where('customer_phone',\$phone)->latest('id')->first();
if (!\$lead) { echo 'LEAD: none'.PHP_EOL; } else {
  echo 'LEAD id='.\$lead->id.' status='.\$lead->status->value.' order_id='.(\$lead->order_id??'null').PHP_EOL;
}
if (!\$order) { echo 'ORDER: none — FAIL'.PHP_EOL; exit(1); }
echo 'ORDER '.\$order->order_code.' sale_id='.\$order->sale_user_id.PHP_EOL;
echo 'awaiting_upsell='.(\$order->isAwaitingLandingUpsell()?'yes':'no').PHP_EOL;
echo 'hold_until='.(\$order->landing_upsell_hold_until?->format('Y-m-d H:i:s')??'null').PHP_EOL;
echo 'items='.\$order->items()->count().' total='.\$order->total.PHP_EOL;
echo 'address='.(\$order->shipping_address??'').PHP_EOL;
"

echo ""
echo "=== WEBHOOK UPSELL ==="
HTTP2=$(curl -s -o /tmp/wh_up.json -w '%{http_code}' -X POST \
  "$BASE/api/v1/landing/$TOKEN/upsell" \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d "{\"submission_id\":\"ladipage-upsell-$(date +%s)\",\"phone\":\"$PHONE\",\"mua_them_1\":\"MUA THÊM 1 Má Hồng Kem: 89K\"}")
echo "HTTP $HTTP2"
cat /tmp/wh_up.json
echo ""

sudo -u www-data php artisan queue:wait-empty --queue=webhooks --timeout=60

echo ""
echo "=== SAU UPSELL ==="
php artisan tinker --execute="
\$phone='$PHONE';
\$order = App\Models\Order::query()->where('customer_phone',\$phone)->latest('id')->first();
\$order->load('items');
echo 'ORDER '.\$order->order_code.' items='.\$order->items->count().' total='.\$order->total.PHP_EOL;
foreach (\$order->items as \$i) {
  echo ' - '.\$i->product_name.' ['.\$i->item_type.'] x'.\$i->quantity.' @'.\$i->unit_price.PHP_EOL;
}
echo 'awaiting_upsell='.(\$order->isAwaitingLandingUpsell()?'yes':'no').PHP_EOL;
echo 'PHONE='.\$phone.PHP_EOL;
"
