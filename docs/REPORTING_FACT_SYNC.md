# Deprecated: full history sync command

`reports:sync-all-facts` has been removed from the runtime schedule and command set.

Use SQL aggregation instead:

```bash
php artisan reports:aggregate-sql --all --queue
php artisan reports:aggregate-sql --from=YYYY-MM-DD --to=YYYY-MM-DD --queue
php artisan reports:aggregate-sql YYYY-MM-DD --company=1
```

Daily mutations are handled by observers and `UpdateDailyFactJob`, rebuilding only the changed company/day.
