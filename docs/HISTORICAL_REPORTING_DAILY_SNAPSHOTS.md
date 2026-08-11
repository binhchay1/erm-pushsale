# Historical reporting daily snapshots

## Mục tiêu

Các dashboard/báo cáo không được quét trực tiếp toàn bộ bảng raw khi người dùng chọn khoảng ngày dài. Hệ thống tách dữ liệu thành 2 tầng:

1. **Ngày đã qua**: đọc từ bảng tổng hợp theo ngày (`report_daily_*_facts`).
2. **Ngày hiện tại hoặc ngày chưa tổng hợp**: query live đúng phần thời gian còn mở.

Nhờ vậy filter 7 ngày / 30 ngày / tháng cũ không còn kéo toàn bộ `inbound_events`, `lead_ingestions`, `orders`, `order_items` lên RAM của request web.

## Luồng schedule hiện có

- `reports:aggregate-daily --queue`: tổng hợp ngày hiện tại mỗi 5 phút.
- `reports:process-dirty --queue`: rebuild các ngày cũ bị webhook/COD/đơn hoàn đánh dấu dirty.
- `reports:aggregate-daily yesterday --close --queue`: đóng sổ ngày hôm qua lúc 00:20.
- `reports:warm-snapshots --queue`: warm kết quả report mặc định sau khi đóng sổ.
- `reports:verify-facts --days=14 --queue`: kiểm tra checksum facts hằng ngày.

## Marketing raw landing packet

Marketing dashboard dùng số liệu **Tổng gói tin nhận được** theo `inbound_events.source = landing_webhook`. Bảng tổng hợp mới:

```txt
report_daily_marketing_packet_facts
```

Bảng này lưu theo ngày và dimension:

- nguồn dữ liệu / landing / landing source
- marketer / team
- kênh quảng cáo
- UTM source / campaign / medium / term / content
- trạng thái raw event
- gói chính / upsale
- phone unique / gửi trùng / lỗi / không hợp lệ

Dashboard Marketing dùng hybrid reader:

```txt
ngày cũ có facts     -> report_daily_marketing_packet_facts
ngày cũ chưa có fact -> inbound_events fallback
ngày hiện tại        -> inbound_events live
```

## Command vận hành

Backfill toàn bộ lịch sử sau khi deploy migration:

```bash
php artisan reports:backfill-facts --from=2026-08-01 --to=2026-08-11 --queue
```

Backfill riêng một công ty:

```bash
php artisan reports:backfill-facts --company=1 --from=2026-08-01 --to=2026-08-11 --queue
```

Build ngay hôm nay để dashboard nhẹ hơn:

```bash
php artisan reports:aggregate-daily today --queue
```

Đóng sổ ngày hôm qua:

```bash
php artisan reports:aggregate-daily yesterday --close --queue
```

Kiểm tra facts:

```bash
php artisan reports:verify-facts --days=14
```

## Lưu ý

- Không xóa dữ liệu raw. Facts chỉ là bảng tổng hợp để đọc nhanh.
- Nếu ngày cũ có late webhook hoặc COD/hoàn cập nhật, observer sẽ đánh dấu dirty date và schedule rebuild đúng ngày đó.
- Nếu filter gồm cả ngày cũ và hôm nay, hệ thống tự cộng facts ngày cũ + live hôm nay.
