# V21 — Horizon queue optimization and reporting pipeline

## Kết luận audit

Các luồng tổng hợp dữ liệu của V18 đã có queue cho phần nặng nhất:

- `reports:aggregate-daily --queue` dispatch `BuildDailyReportFactsJob`.
- `reports:process-dirty --queue` dispatch lại `BuildDailyReportFactsJob` cho đúng ngày bị ảnh hưởng.
- `reports:archive-month --queue` dispatch `ArchiveMonthlyAnalyticsJob` theo từng công ty/tháng.
- `reports:refresh-stale-archives --queue` dispatch lại `ArchiveMonthlyAnalyticsJob` cho tháng stale.

V21 bổ sung nốt các phần trước đó còn chạy inline trong scheduler:

- `reports:warm-snapshots --queue` dispatch `WarmReportSnapshotsForUserJob` theo từng user/date-window.
- `reports:verify-facts --days=14 --queue` dispatch `VerifyReportFactsJob` theo từng company/day.
- `reports:prune-snapshots --queue` dispatch `PruneReportSnapshotsJob` vào queue bảo trì.

Như vậy scheduler chỉ còn nhiệm vụ xếp job nhẹ, không tự chạy query/report nặng trực tiếp.

## Queue báo cáo sau V21

| Queue | Mục đích | Ví dụ job |
| --- | --- | --- |
| `reports-live` | Tổng hợp hôm nay để dashboard vẫn live | `BuildDailyReportFactsJob(finalize=false)` |
| `reports-history` | Backfill, đóng sổ ngày cũ, dirty-date rebuild | `BuildDailyReportFactsJob(finalize=true)` |
| `reports-archive` | Copy bảng nguồn sang bảng tháng và verify checksum | `ArchiveMonthlyAnalyticsJob` |
| `reports-maintenance` | Warm snapshot, verify checksum, prune snapshot | `WarmReportSnapshotsForUserJob`, `VerifyReportFactsJob`, `PruneReportSnapshotsJob` |

`QUEUE_REPORTS=reports-history` vẫn được giữ làm alias backward-compatible cho server cũ.

## Horizon sau V21

Trước V21 project có nhiều supervisor nhỏ, mỗi supervisor `minProcesses=1`. Production có thể luôn giữ khoảng 15 PHP worker idle dù không có job.

V21 gộp lại còn 7 supervisor autoscale:

1. `supervisor-ingestion`
   - `webhooks`
   - `pancake-orders`
   - `pancake-chat`
   - `messages`

2. `supervisor-shipping`
   - `shipping-webhooks`
   - `shipments`

3. `supervisor-broadcasts`
   - `broadcasts-internal-chat`
   - `broadcasts-pancake-chat`
   - `broadcasts-dashboard`
   - `broadcasts-notifications`

4. `supervisor-background`
   - `notifications`
   - `translations`
   - `default`

5. `supervisor-reports-live`
   - `reports-live`

6. `supervisor-reports-batch`
   - `reports-history`
   - `reports-maintenance`
   - `reports-archive`
   - old alias `reports`

7. `supervisor-exports`
   - `exports`

Điểm quan trọng: webhook/lead/chat không chung worker với báo cáo nặng, nên báo cáo bị nghẽn không làm mất realtime ingest.

## Biến môi trường mới

```dotenv
QUEUE_REPORTS_LIVE=reports-live
QUEUE_REPORTS_HISTORY=reports-history
QUEUE_REPORTS_ARCHIVE=reports-archive
QUEUE_REPORTS_MAINTENANCE=reports-maintenance
QUEUE_REPORTS=reports-history

HORIZON_INGESTION_MIN_PROCESSES=2
HORIZON_INGESTION_MAX_PROCESSES=8
HORIZON_SHIPPING_MIN_PROCESSES=1
HORIZON_SHIPPING_MAX_PROCESSES=5
HORIZON_BROADCAST_MIN_PROCESSES=1
HORIZON_BROADCAST_MAX_PROCESSES=4
HORIZON_BACKGROUND_MIN_PROCESSES=1
HORIZON_BACKGROUND_MAX_PROCESSES=3
HORIZON_REPORT_LIVE_MIN_PROCESSES=1
HORIZON_REPORT_LIVE_MAX_PROCESSES=2
HORIZON_REPORT_BATCH_MIN_PROCESSES=1
HORIZON_REPORT_BATCH_MAX_PROCESSES=2
HORIZON_EXPORT_MIN_PROCESSES=1
HORIZON_EXPORT_MAX_PROCESSES=2
```

## Luồng dữ liệu nặng

### Hôm nay

`reports:aggregate-daily --queue` chạy 5 phút/lần. Job vào `reports-live`, max worker thấp để không chiếm CPU của webhook.

### Ngày cũ

Dirty-date hoặc backfill vào `reports-history`. Dữ liệu ngày cũ không bị query trực tiếp từ bảng raw ở từng request, mà đọc từ facts/snapshot.

### Archive theo tháng

`ArchiveMonthlyAnalyticsJob` chạy theo company/month. Bên trong service copy theo `chunkById` và transaction nhỏ:

- `REPORTING_ARCHIVE_COPY_CHUNK_SIZE=2000`
- `REPORTING_ARCHIVE_CHECKSUM_CHUNK_SIZE=1000`

Job vẫn là một job theo tháng để giữ manifest/checksum atomic ở cấp tháng, nhưng bên trong không giữ một transaction lớn cho cả tháng.

## Deploy

```bash
php artisan optimize:clear
php artisan config:cache
php artisan horizon:terminate
```

Sau deploy kiểm tra:

```bash
php artisan queue:wait-empty --queue=reports-live,reports-history,reports-archive,reports-maintenance --timeout=10
php artisan reports:aggregate-daily --queue
php artisan reports:warm-snapshots --queue --company=1
php artisan reports:verify-facts --days=1 --queue --company=1
```
