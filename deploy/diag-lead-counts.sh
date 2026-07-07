#!/usr/bin/env bash
# Phân tích lệch số contact/lead giữa các báo cáo.
set -euo pipefail
cd /var/www/erm-pushsale

DATE="${1:-$(date +%Y-%m-%d)}"
MARKETER="${2:-}"

php artisan tinker --execute="
\$date = '$DATE';
\$from = Illuminate\Support\Carbon::parse(\$date)->startOfDay();
\$to = Illuminate\Support\Carbon::parse(\$date)->endOfDay();

echo '=== LEAD INGESTIONS today (\$date) ===' . PHP_EOL;
\$base = App\Models\LeadIngestion::query()->whereBetween('created_at', [\$from, \$to]);
echo 'total_rows=' . (clone \$base)->count() . PHP_EOL;
echo 'by_status:' . PHP_EOL;
foreach ((clone \$base)->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c','status') as \$s => \$c) {
  echo \"  \$s=\$c\" . PHP_EOL;
}
echo 'upsell_rows=' . (clone \$base)->where('external_id', 'like', '%:upsell')->count() . PHP_EOL;
echo 'duplicate_rows=' . (clone \$base)->where('status', 'duplicate')->count() . PHP_EOL;
echo 'failed_rows=' . (clone \$base)->where('status', 'failed')->count() . PHP_EOL;
echo 'unique_phones=' . (clone \$base)->whereNotIn('status', ['duplicate','failed'])->where(fn(\$q)=>\$q->whereNull('external_id')->orWhere('external_id','not like','%:upsell'))->distinct('customer_phone')->count('customer_phone') . PHP_EOL;
echo 'countable_primary=' . (clone \$base)->whereNotIn('status', ['duplicate','failed'])->where(fn(\$q)=>\$q->whereNull('external_id')->orWhere('external_id','not like','%:upsell'))->count() . PHP_EOL;

echo PHP_EOL . '=== BY MARKETER ===' . PHP_EOL;
\$rows = App\Models\LeadIngestion::query()
  ->join('marketing_sources', 'lead_ingestions.marketing_source_id', '=', 'marketing_sources.id')
  ->join('users', 'marketing_sources.marketer_user_id', '=', 'users.id')
  ->whereBetween('lead_ingestions.created_at', [\$from, \$to])
  ->selectRaw('users.id uid, users.name, COUNT(*) total, SUM(CASE WHEN lead_ingestions.external_id LIKE \"%:upsell\" THEN 1 ELSE 0 END) upsell, SUM(CASE WHEN lead_ingestions.status=\"duplicate\" THEN 1 ELSE 0 END) dup, SUM(CASE WHEN lead_ingestions.status=\"failed\" THEN 1 ELSE 0 END) fail')
  ->groupBy('users.id','users.name')
  ->orderByDesc('total')
  ->get();
foreach (\$rows as \$r) {
  if ('$MARKETER' !== '' && stripos(\$r->name, '$MARKETER') === false) continue;
  echo \$r->name . \" id=\" . \$r->uid . \" total=\" . \$r->total . \" upsell=\" . \$r->upsell . \" dup=\" . \$r->dup . \" fail=\" . \$r->fail . PHP_EOL;
}

echo PHP_EOL . '=== CAMPAIGN contacts column (lifetime DB) vs leads today ===' . PHP_EOL;
\$campaigns = App\Models\MarketingSource::query()->whereNull('parent_id')->orderByDesc('contacts')->limit(8)->get(['id','name','contacts','marketer_user_id']);
foreach (\$campaigns as \$c) {
  \$todayLeads = App\Models\LeadIngestion::query()->where('marketing_source_id', \$c->id)->whereBetween('created_at', [\$from, \$to])->count();
  \$todayPrimary = App\Models\LeadIngestion::query()->where('marketing_source_id', \$c->id)->whereBetween('created_at', [\$from, \$to])
    ->whereNotIn('status', ['duplicate','failed'])
    ->where(fn(\$q)=>\$q->whereNull('external_id')->orWhere('external_id','not like','%:upsell'))
    ->count();
  echo \"#{\$c->id} {\$c->name} lifetime_contacts={\$c->contacts} today_all={\$todayLeads} today_primary={\$todayPrimary}\" . PHP_EOL;
}

echo PHP_EOL . '=== ORDERS data_arrived today ===' . PHP_EOL;
\$orders = App\Models\Order::query()->whereBetween('data_arrived_at', [\$from, \$to]);
echo 'orders=' . \$orders->count() . ' unique_phones=' . (clone \$orders)->distinct('customer_phone')->count('customer_phone') . PHP_EOL;
"
