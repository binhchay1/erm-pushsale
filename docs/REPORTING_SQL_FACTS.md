# Reporting SQL Facts Contract

Reporting facts must not load raw/payload-heavy rows into PHP collections. Aggregation runs inside MySQL with `INSERT INTO ... SELECT ... GROUP BY ... ON DUPLICATE KEY UPDATE`.

## Commands

Aggregate one day, all companies:

```bash
php artisan reports:aggregate-sql 2026-08-11
```

Aggregate one day, one company:

```bash
php artisan reports:aggregate-sql 2026-08-11 --company=1
```

One-time sync for all DB history, detected from existing source tables:

```bash
php artisan reports:aggregate-sql --all --queue
```

Preview only:

```bash
php artisan reports:aggregate-sql --all --dry-run
```

Bounded rebuild:

```bash
php artisan reports:aggregate-sql --from=2026-08-01 --to=2026-08-11 --queue
```

`reports:backfill-facts` is kept as a compatibility wrapper, but it now dispatches the same SQL aggregation jobs instead of rebuilding facts with PHP collection loops.

## Mutation handling

The following observers dispatch `UpdateDailyFactJob(company_id, date)` after commit:

- `OrderObserver`
- `LeadIngestionObserver`
- `InboundEventObserver`

Only the affected company/day is rebuilt. There is no automatic `sync-all-facts` schedule that scans the whole history.

## Hybrid read contract

Reports use:

- closed historical facts from `report_daily_*_facts`
- live/current-day SQL aggregates from source tables with `selectRaw()/groupBy()`
- no `get()->groupBy()` or payload loop for Marketing raw packet totals

Marketing landing packets now use SQL-level JSON extraction for UTM/phone/status dimensions. This means MySQL does the grouping, duplicate-phone count, and packet status count before PHP receives the summarized rows.
