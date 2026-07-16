# Release validation V21

## PHP lint

Đã kiểm tra syntax các file mới/sửa:

- `config/horizon.php`
- `config/saleops.php`
- `routes/console.php`
- `app/Jobs/Reports/*.php`
- `app/Services/Reporting/*.php`
- `app/Console/Commands/*.php`

Kết quả: không có lỗi syntax ở các file mới/sửa.

## Checklist sau deploy

```bash
php artisan optimize:clear
php artisan config:cache
php artisan horizon:terminate
```

Kiểm tra Horizon UI có 7 supervisor:

- `supervisor-ingestion`
- `supervisor-shipping`
- `supervisor-broadcasts`
- `supervisor-background`
- `supervisor-reports-live`
- `supervisor-reports-batch`
- `supervisor-exports`

Smoke test queue báo cáo:

```bash
php artisan reports:aggregate-daily --queue
php artisan reports:warm-snapshots --queue --company=1
php artisan reports:verify-facts --days=1 --queue --company=1
php artisan reports:prune-snapshots --queue --limit=100
php artisan reports:archive-month $(date -d 'last month' +%Y-%m) --company=1 --dry-run
```

## Chưa chạy trong sandbox

Không chạy được PHPUnit runtime vì package không có `vendor/autoload.php` trong môi trường đóng gói.
