# Release validation V18

## Static

- PHP syntax lint cho toàn bộ file V18 mới/sửa.
- Daily facts đủ lead/order/product/cashflow/inventory.
- Dimension hash bao phủ toàn bộ chiều và collapse nhóm COALESCE trùng.
- Scheduler đủ live, dirty, close, warm, verify, monthly archive, stale refresh, prune.
- Tất cả report chính đi qua facts hoặc snapshot router.
- Archive copy chunk, distributed lock, source recheck và full-row SHA-256.
- Snapshot có scope fingerprint, expiration và anti-stampede lock.

## Runtime cần môi trường có vendor/database

```bash
composer install
php artisan migrate:fresh --env=testing
php artisan test --filter=HistoricalReportingV18Test
php artisan test --filter=HistoricalReportingContractV18Test
```

## Production smoke

```bash
php artisan reports:aggregate-daily yesterday --company=1 --close
php artisan reports:verify-facts --company=1 --days=2
php artisan reports:archive-month "$(date -d 'last month' +%Y-%m)" --company=1 --dry-run
php artisan reports:refresh-stale-archives --company=1
php artisan reports:prune-snapshots --limit=100
php artisan horizon:status
php artisan schedule:list
```

Đối chiếu tối thiểu một ngày có Landing chính + upsale, partial delivery, returned, phí hoàn và COD settled trước khi bật router toàn hệ thống.
