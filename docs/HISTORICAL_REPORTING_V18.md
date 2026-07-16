# V18 — Daily facts, snapshot báo cáo và archive raw theo tháng

## 1. Mục tiêu

V18 loại bỏ mô hình “mỗi lần mở báo cáo lại quét toàn bộ orders/leads/order_items/webhook/kho”. Dữ liệu được chia thành bốn lớp:

1. **Raw source of truth** — giữ nguyên từng lead, packet upsale, đơn, dòng hàng, vận đơn, webhook, COD và movement kho.
2. **Daily materialized facts** — số liệu cuối cùng theo ngày và theo các chiều lọc nghiệp vụ.
3. **Report result snapshots** — payload cuối cùng mà giao diện sử dụng, tách theo tenant, user, phạm vi quyền và bộ lọc.
4. **Monthly raw archive** — bản sao nguyên trạng `*_YYYY_MM`, có row count và full-row SHA-256.

Facts và snapshot đều là dữ liệu dẫn xuất, có thể xóa rồi rebuild. Raw data vẫn là nguồn sự thật; archive là lớp kiểm tra/phục hồi, không phải bảng UI query trực tiếp.

## 2. Hot window và historical window

### Hôm nay

- `reports:aggregate-daily --queue` chạy mỗi 5 phút.
- Dashboard chuẩn ghép ngày cũ từ facts với hôm nay từ raw data.
- Report live được giữ trong Redis theo **time bucket 300 giây**. Không đổi cache key theo từng webhook vì cách đó sẽ gây cache stampede khi đơn về liên tục.
- Có distributed lock cho mỗi `company + user + report + filter`; các request đồng thời không cùng chạy một query lớn.
- `?refresh=1` cho phép người có quyền chủ động lấy lại số liệu.

### Ngày quá khứ

- 00:20 đóng sổ ngày hôm qua.
- Một ngày chỉ được đọc từ facts khi closure ở trạng thái `closed` và checksum hợp lệ.
- Kỳ hoàn toàn thuộc quá khứ được lưu thành snapshot DB nén gzip.
- Snapshot có expiry, được prune theo batch và tự bị xóa khi dữ liệu/người dùng/phạm vi quyền liên quan thay đổi.

Các báo cáo phức tạp V15, báo cáo doanh thu Sale/Marketing, CEO, ranking, đối soát, kho, kế toán và dashboard role đều đi qua snapshot router. Với một tổ hợp filter lịch sử chưa từng dùng, hệ thống có thể tính một lần rồi lưu payload cuối cùng; các lần sau không quét raw tables nữa. Các kỳ chuẩn như hôm qua, tháng trước và từ đầu tháng đến hôm qua được prewarm tự động.

## 3. Ngày cũ vẫn có thể thay đổi

Không giả định tuyệt đối rằng ngày cũ bất biến. Đơn cũ vẫn có thể nhận:

- webhook giao/hoàn;
- COD hoặc settlement;
- phí hoàn, bồi thường;
- biên bản nhập hoàn;
- sửa ngày tác nghiệp/chốt/giao;
- thay đổi dòng sản phẩm.

Luồng xử lý:

```text
Model observer
→ xác định toàn bộ ngày nghiệp vụ cũ và mới bị ảnh hưởng
→ upsert report_dirty_dates theo unique company/date
→ closure chuyển dirty
→ xóa final snapshots giao với ngày đó
→ reports:process-dirty rebuild đúng ngày
→ source checksum + fact checksum hợp lệ
→ đóng lại ngày lịch sử
```

Dirty upsert dùng transaction, row lock và atomic increment nên nhiều webhook đồng thời không làm mất tín hiệu. Redis/cache lỗi không được phép rollback thao tác đơn hàng hoặc kho.

Thay đổi ngân sách Landing và `marketing_source_daily_metrics` chỉ invalidate snapshot liên quan; không bắt hệ thống rebuild order/product facts không liên quan.

## 4. Các bảng daily facts

### `report_daily_lead_facts`

Chiều: ngày, platform, trạng thái, packet type, nguồn Marketing, Landing connection/source, Sale, Marketing, team, kho, trạng thái giao và đối soát.

Số liệu:

- packet count;
- lead/contact thật (`counts_as_lead = true`);
- processed/failed/duplicate;
- packet chờ review.

Packet upsale được giữ để audit nhưng không tăng lead.

### `report_daily_order_facts`

Một đơn được materialize theo 8 loại ngày:

- data arrival;
- Sale received;
- care update;
- closing;
- posting;
- next operation;
- delivery update;
- desired delivery.

Chiều: Sale, Marketing, team, source, Landing connection, kho, hãng vận chuyển, trạng thái giao/đối soát/tác nghiệp/chốt.

Số liệu: tổng đơn, open/closed, delivered/partial/returned/cancelled, đơn có upsale, contact, doanh số, VAT, chiết khấu, tiền cọc, COD, phí giao, phí hoàn, bồi thường và dòng tiền.

### `report_daily_product_facts`

Tổng hợp từ từng `order_item`, không nhân toàn bộ giá trị đơn cho từng sản phẩm. Có:

- `item_origin` và `is_upsell`;
- số dòng, số lượng, số đơn;
- gross/net sales;
- doanh thu ghi nhận;
- COGS từ giá vốn snapshot.

### `report_daily_cashflow_facts`

Theo ngày giao thành công, hoàn, COD chuyển về và webhook:

- COD dự kiến/đã thu/đã chuyển;
- phí giao, hoàn, COD, bảo hiểm, phụ phí;
- bồi thường;
- COD mismatch;
- dòng tiền ròng.

### `report_daily_inventory_facts`

Theo kho, sản phẩm và loại movement: nhập, xuất, hoàn, điều chỉnh. Movement có `unit_cost` snapshot nên việc đổi giá nhập sản phẩm sau này không làm sai lịch sử.

## 5. Snapshot và phân quyền

Khóa snapshot gồm:

```text
company_id + user_id + report_key + canonical filter + report scope fingerprint
```

Scope fingerprint gồm role, org level, team, manager, leader flag, permissions và danh sách Sale/Marketing được phép xem. Vì vậy thay đổi quyền hoặc cơ cấu nhân sự không thể tái sử dụng payload cũ rộng quyền hơn.

`ReportAccessScopeObserver` xóa snapshot tenant khi User, Team hoặc Marketing Source thay đổi các trường ảnh hưởng báo cáo.

Snapshot live nằm trong Redis; snapshot final nằm trong `report_query_snapshots` với gzip/base64 JSON. `reports:prune-snapshots` xóa snapshot hết hạn theo batch để bảng không tăng vô hạn.

## 6. Archive raw theo tháng

Ngày 2 hàng tháng, dữ liệu tháng trước được copy sang các bảng:

```text
lead_ingestions_2026_06
inbound_events_2026_06
shipping_webhook_events_2026_06
shipping_status_events_2026_06
activity_logs_2026_06
orders_2026_06
order_items_2026_06
shipments_2026_06
```

Quy trình từng tenant/table:

1. Acquire distributed archive lock.
2. Tạo bảng theo schema nguồn.
3. Copy theo chunk ID, không giữ một transaction khổng lồ.
4. Tính SHA-256 trên toàn bộ nội dung từng row.
5. Đọc lại source count/checksum sau copy.
6. Chỉ verified khi source không thay đổi trong lúc copy và archive khớp hoàn toàn.
7. Ghi manifest.

Nếu source đổi trong lúc archive, trạng thái là `source_changed_retry_required`, tuyệt đối không purge. Nếu dữ liệu tháng đã archive được sửa muộn, observer đổi manifest thành `stale`; job 02:40 tự archive lại theo batch.

SQLite/testing dùng `analytics_cold_records` làm fallback.

### Chính sách purge

Mặc định:

```dotenv
REPORTING_ARCHIVE_ALLOW_PURGE=false
```

`orders`, `order_items`, `shipments` luôn `purge_safe=false` vì còn mutable. Chỉ bảng append-only mới có thể purge khi command có `--purge`, env cho phép, checksum verified và đã có backup/restore drill. Khuyến nghị production vẫn giữ purge tắt.

## 7. Integrity

Khi finalizing một ngày, source checksum được lấy trước và sau aggregate. Nếu dữ liệu thay đổi trong lúc chạy, ngày không được đóng.

`reports:verify-facts` kiểm tra:

- closure tồn tại và closed;
- source checksum hiện tại;
- full fact checksum;
- consistency sau late update.

`--repair` rebuild ngày sai. Monthly archive có manifest riêng, source/archive row count và checksum riêng.

## 8. Scheduler

```text
*/5 phút       aggregate hôm nay
*/10 phút      process dirty dates
00:20          đóng ngày hôm qua
00:45          warm snapshot kỳ chuẩn
01:20          verify facts 14 ngày
02:00 ngày 2   archive tháng trước
02:40 mỗi ngày refresh archive stale
03:20 mỗi ngày prune snapshot hết hạn
*/5 phút       Horizon snapshot
```

Daily/Archive jobs implement unique lock để scheduler lặp không xếp nhiều job trùng cho cùng tenant/date/month.

## 9. Lệnh vận hành

```bash
# Backfill lịch sử theo queue reports
php artisan reports:backfill-facts --from=2025-01-01 --to=2026-07-14 --queue

# Build hoặc đóng một ngày
php artisan reports:aggregate-daily 2026-07-14 --company=1 --close

# Late data
php artisan reports:process-dirty --queue

# Verify/repair
php artisan reports:verify-facts --company=1 --days=90
php artisan reports:verify-facts --company=1 --days=90 --repair

# Prewarm
php artisan reports:warm-snapshots
php artisan reports:warm-snapshots --all-users

# Archive
php artisan reports:archive-month 2026-06 --company=1 --dry-run
php artisan reports:archive-month 2026-06 --company=1
php artisan reports:refresh-stale-archives --queue --limit=12

# Housekeeping
php artisan reports:prune-snapshots --limit=5000
```

## 10. Rollout an toàn

1. Deploy với `REPORTING_FACTS_ENABLED=false`.
2. Chạy migration.
3. Backfill theo tenant/tháng vào queue `reports`, giới hạn Horizon concurrency.
4. Chạy verify.
5. Đối chiếu raw và facts trên ngày có Landing + upsale, partial delivery, returned và COD settled.
6. Bật `REPORTING_FACTS_ENABLED=true`.
7. Theo dõi dirty backlog, closure error, report queue runtime, archive manifest và slow query.
8. Chỉ bật archive sau khi kiểm tra disk; không bật purge mặc định.

Không chạy backfill nhiều năm inline trên production.
