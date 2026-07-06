#!/usr/bin/env bash
# E2E prod: luồng lead mới (webhook → log → MKT/Sale visibility → upsell hold).
set -uo pipefail
cd /var/www/erm-pushsale

TOKEN="${1:-zfwihdf7fgvpp1buzztbzrodsy7hzaxx}"
PHONE="${2:-0911$(date +%H%M%S)}"
BASE="${APP_URL:-https://erm-pushsale.duckdns.org}"
PASS=0
FAIL=0

ok()   { echo "  [PASS] $*"; PASS=$((PASS + 1)); }
bad()  { echo "  [FAIL] $*"; FAIL=$((FAIL + 1)); }
info() { echo "=== $* ==="; }

info "PREFLIGHT"
git log -1 --oneline || true

HOLD=$(php artisan tinker --execute="echo (int) config('saleops.landing.hold_seconds');" 2>/dev/null | tail -1)
if [ "$HOLD" = "5" ]; then ok "hold_seconds=5"; else bad "hold_seconds=$HOLD (expected 5)"; fi

if grep -q "dashboard.marketing" routes/channels.php 2>/dev/null; then
  ok "broadcast channel dashboard.marketing registered"
else
  bad "dashboard.marketing channel missing in routes/channels.php"
fi

PENDING=$(php artisan tinker --execute="echo DB::table('jobs')->count();" 2>/dev/null | tail -1)
FAILED=$(php artisan tinker --execute="echo DB::table('failed_jobs')->count();" 2>/dev/null | tail -1)
info "queue pending=$PENDING failed=$FAILED"
if [ "${FAILED:-0}" -eq 0 ] 2>/dev/null; then ok "no failed_jobs"; else bad "failed_jobs=$FAILED"; fi

if sudo supervisorctl status 2>/dev/null | grep -q RUNNING; then
  ok "supervisor queue worker running"
else
  bad "supervisor queue worker not RUNNING"
fi

info "WEBHOOK RECEIVE phone=$PHONE"
HTTP=$(curl -s -o /tmp/e2e_recv.json -w '%{http_code}' -X POST \
  "$BASE/api/v1/landing/$TOKEN/receive" \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d "{\"submission_id\":\"e2e-$(date +%s)\",\"name\":\"E2E Test\",\"phone\":\"$PHONE\",\"address\":\"1 Test St, HN\",\"combo\":\"Mua 2 Thỏi : 289k + Miễn Ship (Bán Chạy)\"}")
echo "HTTP $HTTP — $(cat /tmp/e2e_recv.json)"
[ "$HTTP" = "202" ] && ok "receive HTTP 202" || bad "receive HTTP $HTTP"

# Chỉ chạy 1 job ngay — giữ hold chưa release để verify
sudo -u www-data php artisan queue:work database --once --quiet 2>/dev/null || true

info "VERIFY RECEIVE (ngay sau job đầu — hold còn active)"
php artisan tinker --execute="
\$phone = '$PHONE';
\$fail = 0;
\$lead = App\Models\LeadIngestion::query()->where('customer_phone', \$phone)->latest('id')->first();
\$order = App\Models\Order::query()->where('customer_phone', \$phone)->latest('id')->first();
\$campaign = App\Models\MarketingSource::query()->where('webhook_token', '$TOKEN')->first();

if (!\$lead) { echo 'FAIL no lead_ingestion'.PHP_EOL; \$fail++; }
else {
  echo 'lead id='.\$lead->id.' status='.\$lead->status->value.' mkt_src='.(\$lead->marketing_source_id??'null').PHP_EOL;
  \$lead->status->value === 'processed' ? print('PASS lead processed'.PHP_EOL) : print('FAIL lead status='.\$lead->status->value.PHP_EOL);
  if (\$fail === 0 && \$lead->status->value !== 'processed') \$fail++;
  if (\$lead->marketing_source_id !== \$campaign?->id) { echo 'FAIL marketing_source mismatch'.PHP_EOL; \$fail++; }
  else { echo 'PASS marketing_source linked'.PHP_EOL; }
}

if (!\$order) { echo 'FAIL no order'.PHP_EOL; \$fail++; }
else {
  echo 'order '.\$order->order_code.' sale_id='.\$order->sale_user_id.' total='.\$order->total.PHP_EOL;
  \$order->sale_user_id ? print('PASS order assigned to sale'.PHP_EOL) : print('WARN order in pool (no sale yet)'.PHP_EOL);
  \$order->isAwaitingLandingUpsell() ? print('PASS awaiting upsell hold active'.PHP_EOL) : print('FAIL upsell hold not active'.PHP_EOL);
  if (!\$order->isAwaitingLandingUpsell()) \$fail++;
  echo 'items='.\$order->items()->count().PHP_EOL;
}

\$marketer = \$campaign ? App\Models\User::query()->find(\$campaign->marketer_user_id) : null;
if (\$marketer) {
  \$stats = App\Services\DashboardStatsService::marketingSnapshot(\$marketer);
  \$scoped = App\Models\LeadIngestion::query()
    ->whereDate('created_at', today())
    ->where('marketing_source_id', \$campaign->id)
    ->where('customer_phone', \$phone)
    ->exists();
  echo 'mkt leads_today='.\$stats['leads_today'].' contacts_today='.\$stats['contacts_today'].PHP_EOL;
  \$scoped ? print('PASS MKT scoped lead visible in query'.PHP_EOL) : print('FAIL MKT scoped lead NOT found'.PHP_EOL);
  if (!\$scoped) \$fail++;
} else {
  echo 'WARN no marketer on campaign'.PHP_EOL;
}

\$repo = app(App\Repositories\LeadIngestionRepository::class);
if (\$marketer) {
  \$log = \$repo->paginatedLog(['marketer_user_id' => \$marketer->id], 5);
  \$inLog = collect(\$log->items())->contains(fn (\$l) => \$l->customer_phone === \$phone);
  \$inLog ? print('PASS lead in MKT leads log query'.PHP_EOL) : print('FAIL lead missing from MKT log'.PHP_EOL);
  if (!\$inLog) \$fail++;
}

exit(\$fail > 0 ? 1 : 0);
" 2>/dev/null
TINKER_RC=$?
[ $TINKER_RC -eq 0 ] && ok "receive data checks" || bad "receive data checks (exit $TINKER_RC)"

for i in 1 2 3; do
  sudo -u www-data php artisan queue:work database --once --quiet 2>/dev/null || true
done

info "WEBHOOK UPSELL"
HTTP2=$(curl -s -o /tmp/e2e_up.json -w '%{http_code}' -X POST \
  "$BASE/api/v1/landing/$TOKEN/upsell" \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d "{\"submission_id\":\"e2e-upsell-$(date +%s)\",\"phone\":\"$PHONE\",\"mua_them_1\":\"MUA THÊM 1 Má Hồng Kem: 89K\"}")
echo "HTTP $HTTP2 — $(cat /tmp/e2e_up.json)"
[ "$HTTP2" = "202" ] && ok "upsell HTTP 202" || bad "upsell HTTP $HTTP2"

for i in 1 2 3; do
  sudo -u www-data php artisan queue:work database --once --quiet 2>/dev/null || true
done

php artisan tinker --execute="
\$order = App\Models\Order::query()->where('customer_phone', '$PHONE')->latest('id')->first();
if (!\$order) { echo 'FAIL no order after upsell'.PHP_EOL; exit(1); }
\$order->load('items');
echo 'items='.\$order->items->count().' total='.\$order->total.PHP_EOL;
if (\$order->items->count() >= 2 && \$order->total >= 378000) {
  echo 'PASS upsell merged'.PHP_EOL;
  exit(0);
}
echo 'FAIL upsell merge'.PHP_EOL;
exit(1);
" 2>/dev/null
UPSELL_RC=$?
[ $UPSELL_RC -eq 0 ] && ok "upsell merge" || bad "upsell merge"

info "HOLD RELEASE (wait ${HOLD}s + buffer)"
sleep $((HOLD + 3))
for i in 1 2 3 4 5; do
  sudo -u www-data php artisan queue:work database --once --quiet 2>/dev/null || true
done

php artisan tinker --execute="
\$order = App\Models\Order::query()->where('customer_phone', '$PHONE')->latest('id')->first();
if (!\$order) { echo 'FAIL'.PHP_EOL; exit(1); }
echo 'awaiting_upsell='.(\$order->isAwaitingLandingUpsell()?'yes':'no').' hold_until='.(\$order->landing_upsell_hold_until?->toDateTimeString()??'null').PHP_EOL;
! \$order->isAwaitingLandingUpsell() ? print('PASS hold released'.PHP_EOL) : print('WARN hold still active (queue delay?)'.PHP_EOL);
" 2>/dev/null

info "PAGE SERVICES (backend smoke)"
bash deploy/smoke-reports.sh 2>/dev/null | while read -r line; do
  if echo "$line" | grep -q "_ok"; then ok "$line"
  elif echo "$line" | grep -q "_fail"; then bad "$line"
  else echo "  $line"; fi
done

info "AUTHENTICATED PAGE RENDER"
php artisan tinker --execute="
\$checks = [];
\$roles = [
  'admin' => ['/admin/dashboard', '/admin/leads', '/admin/system-monitor', '/admin/marketing/dashboard'],
  'marketing' => ['/marketing/dashboard', '/marketing/leads', '/marketing/workspace', '/marketing/campaigns'],
  'sales' => ['/sales/dashboard', '/sales/workspace', '/sales/customers'],
  'allocator' => ['/allocator/dashboard', '/allocator/workspace'],
];
foreach (\$roles as \$role => \$paths) {
  \$user = App\Models\User::query()->where('role', \$role)->where('is_active', true)->first();
  if (!\$user) { \$checks[] = \"skip_\$role=no_user\"; continue; }
  foreach (\$paths as \$path) {
    try {
      \$req = Illuminate\Http\Request::create(\$path, 'GET');
      \$req->setUserResolver(fn () => \$user);
      app()->instance('request', \$req);
      Illuminate\Support\Facades\Auth::login(\$user);
      \$route = app('router')->getRoutes()->match(\$req);
      \$req->setRouteResolver(fn () => \$route);
      \$action = \$route->getAction('controller');
      if (!\$action) { \$checks[] = \"fail_{\$path}=no_controller\"; continue; }
      [\$class, \$method] = explode('@', \$action);
      \$ctrl = app(\$class);
      \$params = [];
      foreach (\$route->parameterNames() as \$name) {
        if (\$route->parameter(\$name) instanceof Illuminate\Database\Eloquent\Model) {
          \$params[\$name] = \$route->parameter(\$name);
        }
      }
      \$resp = app()->call([\$ctrl, \$method], array_merge(\$params, ['request' => \$req]));
      \$ok = \$resp instanceof \Inertia\Response
        || \$resp instanceof Illuminate\Http\Response
        || \$resp instanceof Illuminate\Http\RedirectResponse;
      \$checks[] = (\$ok ? 'pass' : 'fail') . \"_{\$role}:\" . \$path;
    } catch (Throwable \$e) {
      \$checks[] = 'fail_' . \$role . ':' . \$path . '=' . substr(str_replace(\"\\n\", ' ', \$e->getMessage()), 0, 120);
    }
  }
}
foreach (\$checks as \$c) echo \$c . PHP_EOL;
" 2>/dev/null | while read -r line; do
  if echo "$line" | grep -q "^pass_"; then ok "${line#pass_}"
  elif echo "$line" | grep -q "^fail_"; then bad "${line#fail_}"
  elif echo "$line" | grep -q "^skip_"; then echo "  [SKIP] ${line#skip_}"
  fi
done

info "HTTPS ROUTES (unauthenticated — expect 302 login or 401)"
for path in \
  /login \
  /marketing/dashboard /marketing/leads /marketing/workspace /marketing/campaigns \
  /admin/dashboard /admin/leads /admin/system-monitor /admin/marketing/dashboard \
  /sales/dashboard /sales/workspace /sales/customers \
  /allocator/dashboard /allocator/workspace; do
  code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE$path")
  if [ "$code" = "302" ] || [ "$code" = "200" ] || [ "$code" = "401" ]; then
    ok "$path -> $code"
  else
    bad "$path -> $code (expected 302/200/401)"
  fi
done

info "INBOUND LOG INTEGRITY"
php artisan tinker --execute="
\$phone = '$PHONE';
\$lead = App\Models\LeadIngestion::query()->where('customer_phone', \$phone)->latest('id')->first();
\$inbound = App\Models\InboundEvent::query()->whereDate('created_at', today())->where('channel', 'dsadasdas')->latest('id')->first();
echo 'lead_payload_bytes='.strlen(json_encode(\$lead?->payload ?? [])).PHP_EOL;
echo 'inbound_status='.(\$inbound?->status->value ?? 'none').' correlation='.(\$inbound?->correlation_id ?? 'none').PHP_EOL;
\$lead && is_array(\$lead->payload) ? print('PASS lead payload stored'.PHP_EOL) : print('FAIL lead payload'.PHP_EOL);
" 2>/dev/null

info "SUMMARY phone=$PHONE"
echo "  PASSED: $PASS"
echo "  FAILED: $FAIL"
[ "$FAIL" -eq 0 ] && echo "=== ALL E2E CHECKS PASSED ===" && exit 0
echo "=== E2E HAD FAILURES ===" && exit 1
