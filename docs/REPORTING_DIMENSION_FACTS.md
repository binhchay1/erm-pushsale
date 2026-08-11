# Reporting dimension facts contract

## Why date-only totals are not enough

A daily total table such as `date -> total` cannot answer dashboard filters like warehouse,
team, source, product, shipping status, operation stage, customer type, UTM, or marketer.
For large data, every historical report must read from a **fact table keyed by date + filter
dimensions**, not from the raw operational tables.

## Query rule

All reports follow the same hybrid rule:

1. **Closed historical dates** use daily fact tables.
2. **Today / open dates** use live queries because data can still change.
3. **Historical dates missing facts** use chunked live fallback and should be backfilled.
4. **Detail rows / free-text search** stay live and paginated because they need row-level data.

This lets a range such as `2026-08-01 -> 2026-08-12` read:

```text
2026-08-01 -> 2026-08-11: report_daily_*_facts
2026-08-12: live operational tables
```

## Fact families

The active coverage map is defined in `config/reporting_dimensions.php`.

| Fact family | Table | Purpose |
| --- | --- | --- |
| Marketing packets | `report_daily_marketing_packet_facts` | raw landing packets, UTM, source, marketer/team, packet status |
| Leads | `report_daily_lead_facts` | lead/contact processing, duplicate/review/failed counts |
| Orders | `report_daily_order_facts` | sales/revenue/order status, warehouse, sale, marketer, customer type, operation dimensions |
| Products | `report_daily_product_facts` | product/parent product, item quantity, upsell line, product revenue/cost |
| Cashflow | `report_daily_cashflow_facts` | COD, shipping fees, reconciliation and carrier cost events |
| Inventory | `report_daily_inventory_facts` | stock movements by day, warehouse, product and movement type |

## Filters covered by facts

Facts now store ordinary analytical dimensions, including:

- date type / date basis
- sale, marketer, team, team leader scope
- marketing source, landing connection/source, UTM, ad channel
- warehouse, shipping provider, shipping method
- delivery status, reconciliation status, closing status
- customer type, duplicate phone flag
- operation stage/result
- warehouse care status, printed status, deposit status
- product and parent product in product facts

## Filters that intentionally stay live

The following filters require row-level or text matching and should remain live, indexed and paginated:

- free-text search (`search`)
- exact order id (`order_id`)
- tracking alert derived from shipment/error state
- care status derived from multiple status/time rules
- operation activity status based on history existence
- min/max total product quantity via line aggregation
- hide rows without phone
- no closing date limit

## Operational commands

After deployment:

```bash
php artisan migrate --force
php artisan reports:backfill-facts --from=2026-08-01 --to=2026-08-11 --queue
php artisan reports:audit-fact-coverage
```

Daily scheduler already rebuilds hot and dirty dates:

```text
reports:aggregate-daily --queue                         every 5 minutes
reports:process-dirty --queue                           every 10 minutes
reports:aggregate-daily yesterday --close --queue        00:20
reports:warm-snapshots --queue                          00:45
reports:verify-facts --days=14 --queue                  01:20
```

## Design constraint

A report must not load whole historical ranges into PHP collections. Summary/dashboard
numbers must aggregate in SQL or from fact tables. Detail dialogs may read live records only
with pagination or cursor/chunk processing.
