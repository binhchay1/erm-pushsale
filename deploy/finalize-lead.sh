#!/usr/bin/env bash
set -euo pipefail
cd /var/www/erm-pushsale
PHONE="${1:-0911777666}"
echo "=== Drain queue + finalize pending lead for $PHONE ==="
for i in 1 2 3 4 5 6 7 8 9 10; do
  sudo -u www-data php artisan queue:work database --once --quiet || true
done
php artisan tinker --execute="
\$lead = App\Models\LeadIngestion::query()->where('customer_phone', '$PHONE')->latest('id')->first();
if (!\$lead) { echo 'no_lead'; exit; }
echo 'lead_status='.\$lead->status->value.PHP_EOL;
if (\$lead->status->value === 'gathering') {
  app(App\Services\Leads\LeadIngestionService::class)->finalizeGatheringLead(\$lead->fresh());
  echo 'finalized'.PHP_EOL;
}
\$lead->refresh();
echo 'after='.\$lead->status->value.' order_id='.(\$lead->order_id ?? 'null').PHP_EOL;
"
