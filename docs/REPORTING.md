# Reporting — facts & hybrid reads

Contract for historical dashboards. Config: `config/reporting.php`, `config/reporting_dimensions.php`. Schedule: `routes/console.php`.

## Hybrid rule

1. **Closed historical dates** → `report_daily_*_facts`
2. **Today / open dates** → live SQL aggregates (`selectRaw` / `groupBy`)
3. **Missing facts** → chunked live fallback + backfill
4. **Detail / free-text search** → live + paginated (row-level)

Do **not** load full historical ranges into PHP collections for summary totals.

## Fact families

| Family | Table |
| --- | --- |
| Marketing packets | `report_daily_marketing_packet_facts` |
| Leads | `report_daily_lead_facts` |
| Orders | `report_daily_order_facts` |
| Products | `report_daily_product_facts` |
| Cashflow | `report_daily_cashflow_facts` |
| Inventory | `report_daily_inventory_facts` |

Coverage map: `config/reporting_dimensions.php`. Audit: `php artisan reports:audit-fact-coverage`.

## Schedule (production)

```text
reports:aggregate-daily --queue                 every 5 minutes
reports:process-dirty --queue                   every 10 minutes
reports:aggregate-daily yesterday --close       00:20
reports:warm-snapshots --queue                  00:45
reports:verify-facts --days=14 --queue          01:20
```

Mutations (Order / LeadIngestion / InboundEvent observers) dispatch `UpdateDailyFactJob` for the affected company/day only.  
`reports:sync-all-facts` is **removed** — do not reintroduce.

## SQL aggregation commands

```bash
php artisan reports:aggregate-sql 2026-08-11
php artisan reports:aggregate-sql 2026-08-11 --company=1
php artisan reports:aggregate-sql --all --queue
php artisan reports:aggregate-sql --from=2026-08-01 --to=2026-08-11 --queue
php artisan reports:aggregate-sql --all --dry-run
```

`reports:backfill-facts` remains as a thin wrapper that dispatches the same SQL jobs.

## Live-only filters

Stay live (indexed + paginated): free-text `search`, exact `order_id`, tracking/care derived flags, operation-history existence, min/max product qty via lines, hide-no-phone, no-closing-date-limit.
