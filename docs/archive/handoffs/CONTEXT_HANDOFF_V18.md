# Context handoff V18

V18 là full source tích lũy từ V17, bổ sung tầng dữ liệu báo cáo hiệu năng cao.

## Thành phần

- `config/reporting.php`
- migration daily facts/snapshot/archive
- `DailyReportAggregator`
- `ReportFactReader` + `ReportMetricService`
- `ReportSnapshotStore` + `ReportSnapshotCache`
- `ReportDateObserver`, `ReportAccessScopeObserver`, `ReportDateDirtyTracker`
- `MonthlyArchiveService`
- jobs/commands trong `app/Jobs/Reports` và `app/Console/Commands`
- scheduler trong `routes/console.php`

## Quy tắc

- Raw data là source of truth; facts/snapshot luôn rebuild được.
- Upsale không tăng lead nhưng tăng quantity/revenue/COGS.
- Tiền là integer VND.
- Không dùng facts nếu thiếu closure hoặc closure dirty/error.
- Late webhook/COD/return phải reopen đúng ngày và xóa snapshot liên quan.
- Budget/config chỉ invalidate result snapshot, không rebuild facts không liên quan.
- Snapshot phải có user scope fingerprint; không bỏ phần này.
- UI không query bảng archive động; archive dành cho audit/restore.
- Purge mặc định tắt.
- Archive chỉ verified khi source ổn định trước/sau copy.

## Deploy

```bash
php artisan down
php artisan migrate --force
php artisan optimize:clear
php artisan up
php artisan horizon:terminate

# Giữ REPORTING_FACTS_ENABLED=false trong lúc backfill
php artisan reports:backfill-facts --from=2025-01-01 --to=2026-07-14 --queue
php artisan reports:verify-facts --days=365
php artisan reports:warm-snapshots
```

Production cần `schedule:run` mỗi phút và Horizon queue `reports`. Sau deploy kiểm tra:

```bash
php artisan schedule:list
php artisan horizon:status
php artisan reports:process-dirty --limit=10
php artisan reports:archive-month 2026-06 --company=1 --dry-run
```
