# Landing → trang cảm ơn → gộp upsell tuyệt đối trong 90 giây

## Quy tắc nghiệp vụ đã chốt

1. **Gói form đầu tiên** là một lead thật (`packet_type=lead`, `counts_as_lead=true`). Hệ thống chia sale đúng một lần và tạo order ngay khi worker xử lý.
2. Order mở một cửa sổ gộp tuyệt đối 90 giây tính từ lúc lead chính được tạo. Heartbeat, reload trang hoặc packet bổ sung không kéo dài deadline.
3. Mọi packet mua thêm/follow-up trong cửa sổ chỉ append `order_items` vào cùng order, giữ nguyên sale, và được lưu thành audit packet (`counts_as_lead=false`).
4. Packet đến sau deadline không tự gộp, không chia sale lại, không tạo contact mới. Nó được liên kết với order gốc và đưa vào trạng thái **Cần kiểm tra**.
5. Packet upsell đến trước packet chính do queue chạy lệch thứ tự được giữ ở trạng thái gathering trong thời gian ngắn. Khi base tới, backend tự reconcile; nếu base không tới thì trở thành orphan upsell cần kiểm tra.
6. `customer_note` chỉ chứa nội dung khách nhập/ghi chú giao hàng. Tên sản phẩm luôn nằm ở `order_items`, không được nối vào cột Tin nhắn.
7. Nhật ký lead hiển thị mỗi packet để audit. Dashboard và toàn bộ báo cáo chỉ đếm `counts_as_lead=true`, vì vậy năm packet của cùng khách vẫn chỉ là một lead/contact.

## Cross-domain và CORS

Hệ thống nhận dữ liệu từ nhiều landing nên endpoint dùng:

```php
'allowed_origins' => ['*'],
'supports_credentials' => false,
```

CORS chỉ cho phép JavaScript trình duyệt đọc response; nó **không phải cơ chế xác thực webhook**. Các lớp bảo vệ thật gồm campaign token khó đoán, rate limit, giới hạn payload, validation, honeypot, idempotency, row lock và audit log.

Hai trang khác origin không chia sẻ localStorage. JS campaign hand-off opaque reference theo thứ tự:

1. query/hidden field (`session_id`, `saleops_client_ref`, `parent_ref`),
2. `window.name` trong cùng tab,
3. localStorage chỉ là fallback trên trang cảm ơn cùng origin.

Landing chính luôn sinh session/reference mới cho mỗi lượt mở, không tái sử dụng storage của khách trước. Điều này ngăn hai khách dùng cùng máy bị gộp nhầm.

## Cấu hình

```dotenv
LEAD_HOLD_SECONDS=90
LEAD_MAX_HOLD_SECONDS=90
LEAD_GROUPING_WINDOW_MINUTES=15
QUEUE_CONNECTION=redis
```

Sau khi đổi cấu hình:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan horizon:terminate
```

## Endpoint LadiPage

Form chính:

```text
POST {APP_URL}/api/v1/landing/{CAMPAIGN_TOKEN}/receive
```

Trang cảm ơn/upsell:

```text
POST {APP_URL}/api/v1/landing/{CAMPAIGN_TOKEN}/upsell
```

Hai form phải dùng cùng campaign token và cùng JS được sinh từ màn Chiến dịch. JS tự thêm:

```text
session_id
saleops_client_ref
parent_submission_id
parent_ref
is_upsell=1
item_type=upsell
```

Endpoint `/receive` cũng tự nhận diện packet bổ sung qua `mua_them_*`, `upsell_*`, `addon_*`, `is_upsell` hoặc `item_type=upsell`; vì vậy cấu hình nhầm endpoint không làm packet bị đếm thành lead mới.

## Trạng thái packet

| packet_type | counts_as_lead | Ý nghĩa |
|---|---:|---|
| `lead` | true | Form chính, được tính trong dashboard/báo cáo |
| `follow_up` | false | Gói bổ sung gửi qua `/receive` |
| `upsell` | false | Gói đã gộp thành công vào order |
| `late_upsell` | false | Đến sau deadline, liên kết order gốc, cần review |
| `orphan_upsell` | false | Không tìm được base/order, cần review |

`duplicate`, `failed`, `needs_review` không được tính là contact.

## Xử lý late/orphan upsell

Trang Nhật ký lead và dialog ở Hồ sơ khách hàng cho phép người có quyền:

- **Gộp vào đơn gốc** nếu đơn chưa chốt, chưa trừ kho, chưa có vận đơn/shipment.
- **Tạo đơn mua thêm** nếu đơn gốc không còn an toàn để sửa. Đơn mới giữ nguyên sale/team/source và có badge liên kết mã đơn gốc.
- **Đã kiểm tra/không xử lý** để đóng cảnh báo mà không làm mất payload.

Quyền xử lý:

- Admin và Allocator/`leads:full`: mọi packet.
- Sale đang phụ trách order: packet của order đó.
- Trưởng nhóm/trưởng bộ phận sale: order cùng team.
- Kho, Marketing, Kế toán và sale không liên quan: chỉ xem.

## Tác động lên các bộ phận

- **Sale/Hồ sơ khách hàng:** trong 90 giây chỉ thấy một order với nhiều item. Đơn mua thêm tạo thủ công sau deadline có badge và mã đơn gốc.
- **Nhật ký lead:** có nhiều dòng packet là đúng để audit; cột loại gói và “Có tính lead” cho biết dòng nào ảnh hưởng báo cáo.
- **Marketing/Dashboard/CEO/Campaign/Allocator:** contact lấy từ `counts_as_lead=true`; đơn supplemental vẫn cộng doanh thu/sản lượng nhưng không tăng lead.
- **Kho/Kế toán/Vận chuyển:** chỉ nhận order mới khi người có quyền chủ động chọn “Tạo đơn mua thêm”; không có order ngầm sinh từ packet muộn.

## Payload mẫu

Form chính:

```json
{
  "submission_id": "landing-001",
  "name": "Nguyễn Văn A",
  "phone": "0912345678",
  "address": "Hà Nội",
  "message": "Giao giờ hành chính",
  "combo": "Mua 1 Thỏi: 149k + 30k Ship",
  "session_id": "opaque-session-id",
  "saleops_client_ref": "opaque-client-ref"
}
```

Upsell:

```json
{
  "submission_id": "upsell-001",
  "parent_submission_id": "opaque-client-ref",
  "session_id": "opaque-session-id",
  "is_upsell": 1,
  "mua_them_1": "1 hộp Bàn Chải (8 chiếc): 69k"
}
```

## Đối soát DB

```sql
SELECT id, external_id, status, packet_type, counts_as_lead,
       parent_ingestion_id, order_id, related_order_id,
       requires_review, reviewed_at
FROM lead_ingestions
WHERE customer_phone = '0912345678'
ORDER BY id;

SELECT id, order_code, sale_user_id, customer_phone, customer_note,
       total, landing_upsell_hold_until, landing_upsell_locked
FROM orders
WHERE customer_phone = '0912345678'
ORDER BY id;

SELECT order_id, product_name, quantity, unit_price, item_type
FROM order_items
WHERE order_id IN (:ORDER_IDS)
ORDER BY order_id, id;
```

Kết quả mong đợi trong 90 giây: một row `counts_as_lead=true`, các packet còn lại false, một order, nhiều order item, và sale không đổi.

## Deploy

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build

php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan horizon:terminate
```

Migration có backfill dữ liệu Landing cũ, dọn các dòng `[Upsale]`/`SP:` từng bị nối vào `customer_note`, và tính lại `marketing_sources.contacts` theo lead chính.
