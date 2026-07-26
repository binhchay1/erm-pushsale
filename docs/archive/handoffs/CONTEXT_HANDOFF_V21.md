# Context handoff V21

V21 tập trung vào queue/Horizon và đường ống báo cáo hiệu năng cao.

## Source base

Full source tích lũy từ V20.

## Thay đổi chính

- `config/horizon.php` đã gộp 15 supervisor cũ thành 7 supervisor autoscale.
- Thêm queue báo cáo tách biệt:
  - `reports-live`
  - `reports-history`
  - `reports-archive`
  - `reports-maintenance`
- Sửa `BuildDailyReportFactsJob`:
  - hôm nay + chưa finalize -> `reports-live`;
  - ngày cũ/finalize/backfill/dirty -> `reports-history`.
- Sửa `ArchiveMonthlyAnalyticsJob` -> `reports-archive`.
- Thêm `WarmReportSnapshotsForUserJob` và `ReportSnapshotWarmupService`.
- Thêm `VerifyReportFactsJob`.
- Thêm `PruneReportSnapshotsJob`.
- Scheduler giờ dùng `--queue` cho warm snapshot, verify facts và prune snapshots.
- `.env.example` đã cập nhật biến queue và Horizon mới.

## File mới

- `app/Services/Reporting/ReportSnapshotWarmupService.php`
- `app/Jobs/Reports/WarmReportSnapshotsForUserJob.php`
- `app/Jobs/Reports/VerifyReportFactsJob.php`
- `app/Jobs/Reports/PruneReportSnapshotsJob.php`
- `docs/HORIZON_QUEUE_OPTIMIZATION_V21.md`
- `docs/CONTEXT_HANDOFF_V21.md`
- `docs/RELEASE_VALIDATION_V21.md`

## File sửa

- `config/horizon.php`
- `config/saleops.php`
- `.env.example`
- `routes/console.php`
- `app/Jobs/Reports/BuildDailyReportFactsJob.php`
- `app/Jobs/Reports/ArchiveMonthlyAnalyticsJob.php`
- `app/Console/Commands/WarmReportSnapshotsCommand.php`
- `app/Console/Commands/VerifyReportFactsCommand.php`
- `app/Console/Commands/PruneReportSnapshotsCommand.php`

## Ghi chú vận hành

Sau deploy bắt buộc `php artisan horizon:terminate` để Horizon nhận cấu hình supervisor mới.

Không cần chạy legacy `queue:work` song song với Horizon.
