# Landing → trang cảm ơn → gộp upsell trong 90 giây

## Mục tiêu nghiệp vụ

- Form chính tạo lead, chia sale và tạo order **ngay khi worker xử lý gói đầu**.
- Order được đánh dấu `landing_upsell_hold_until` trong 90 giây.
- Form upsell gửi gói thứ hai đến endpoint `/upsell`.
- Nếu gói thứ hai xác định đúng order gốc và order còn mở thì sản phẩm được append vào **cùng order**.
- Hết 90 giây, hoặc sale đã gọi/cập nhật/chốt/sửa order, order không nhận gộp nữa.
- Không trì hoãn tạo order 90 giây. Queue delay chỉ dùng để đóng cờ `open merge` đúng deadline.

## Hai landing dùng để kiểm thử

- Form chính: `https://www.tienichgiadinh2.click/dasdsdasdada`
- Trang cảm ơn/upsell: `https://www.hangngon23.click/camonphanbatsang`

Hai trang khác origin. Không được chỉ dựa vào `localStorage`, vì storage không được chia sẻ giữa hai domain. JS campaign dùng ba lớp hand-off:

1. query/hidden field (`session_id`, `saleops_client_ref`, `parent_ref`),
2. `window.name` chứa opaque ID (không chứa PII),
3. localStorage chỉ làm fallback trong cùng origin.

## Cấu hình server

```dotenv
LEAD_HOLD_SECONDS=90
LEAD_MAX_HOLD_SECONDS=90
LEAD_GROUPING_WINDOW_MINUTES=15
LANDING_ALLOWED_ORIGINS=https://www.tienichgiadinh2.click,https://www.hangngon23.click
QUEUE_CONNECTION=redis
```

Sau khi đổi `.env`:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan horizon:terminate
```

## Cấu hình LadiPage

### Form chính

Webhook:

```text
POST {APP_URL}/api/v1/landing/{CAMPAIGN_TOKEN}/receive
```

Bật **Auto Funnel**, redirect đến trang cảm ơn và dán JS được sinh trong màn Chiến dịch vào custom JS của trang.

Tên field nên ổn định:

```text
name
phone
address
combo
mua_them_1
mua_them_2
message
```

### Form trang cảm ơn

Webhook:

```text
POST {APP_URL}/api/v1/landing/{CAMPAIGN_TOKEN}/upsell
```

Dùng **cùng campaign token** với form chính và dán cùng JS campaign. Các hidden field sau được JS tự gắn:

```text
session_id
saleops_client_ref
parent_submission_id
parent_ref
```

Form upsell không bắt buộc hỏi lại SĐT nếu có một trong các reference trên.

## Luồng backend chi tiết

1. Request được rate limit và lưu `inbound_events` (header nhạy cảm được mask, payload quá lớn không lưu nguyên bản).
2. Controller kiểm tra campaign token, trạng thái campaign, payload size, honeypot và identity.
3. `ProcessLeadIngestionJob` chạy queue `webhooks`.
4. Driver chuẩn hóa tên, SĐT, địa chỉ, combo, add-on, giá và discount.
5. Gói form chính:
   - chống retry bằng `external_id`;
   - tạo `lead_ingestions`;
   - chia sale/tạo order ngay;
   - ghi `landing_upsell_hold_until = now + 90s`;
   - dispatch `FinalizeLandingLeadJob` tại deadline.
6. Gói upsell:
   - chống retry bằng namespace `external_id:upsell`;
   - tìm order theo session, parent/client ref, sau cùng là cùng campaign + SĐT;
   - bắt buộc order chưa bị sale lock và deadline còn tương lai;
   - lock row `FOR UPDATE`, kiểm tra deadline lần nữa;
   - append `order_items`, tính lại subtotal/discount/total;
   - tạo một ingestion audit trỏ về cùng order;
   - broadcast/notification cho sale.
7. Job finalize chạy ở deadline và xóa trạng thái mở gộp. Nếu queue chạy sớm do sync/clock drift, job tự đặt lại đúng deadline thay vì đóng sớm.

## Quy tắc sau 90 giây

Gói upsell không được append vào order cũ. Nếu có SĐT hợp lệ, hệ thống ghi nhận theo luồng lead mới/duplicate để vận hành kiểm tra. Nếu không có SĐT và reference không còn trỏ tới order mở, gói bị ghi Failed thay vì đoán nhầm khách.

## Payload mẫu

Form chính:

```json
{
  "submission_id": "landing-001",
  "name": "Nguyễn Văn A",
  "phone": "0912345678",
  "address": "Hà Nội",
  "combo": "Mua 1 Thỏi : 149k + 30k Ship",
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
  "mua_them_1": "1 hộp Bàn Chải (8 chiếc): 69k"
}
```

## Checklist test thật

1. Mở DevTools → Network ở form chính.
2. Submit số điện thoại test và một combo.
3. Xác nhận `/receive` trả HTTP 202 và lấy `correlation_id`.
4. Xác nhận URL chuyển trang hoặc form trang cảm ơn có hidden `session_id` + `parent_ref`.
5. Trong vòng 90 giây chọn bàn chải, submit.
6. Xác nhận `/upsell` trả HTTP 202.
7. DB phải có đúng một `orders`, hai `lead_ingestions` và ít nhất hai `order_items`.
8. Lặp lại cùng `submission_id` upsell: item không được nhân đôi.
9. Test khách mới, chờ hơn 90 giây rồi submit upsell: order ban đầu không đổi.
10. Sale gọi/cập nhật order trong 90 giây rồi submit upsell: order ban đầu không đổi vì đã lock.

SQL kiểm tra nhanh:

```sql
SELECT id, order_code, customer_phone, total,
       landing_upsell_hold_until, landing_upsell_locked
FROM orders
WHERE customer_phone = '0912345678'
ORDER BY id DESC;

SELECT id, external_id, status, order_id, error_message
FROM lead_ingestions
WHERE customer_phone = '0912345678'
ORDER BY id DESC;

SELECT order_id, product_name, quantity, unit_price, item_type
FROM order_items
WHERE order_id = :ORDER_ID;
```

## Điểm cần sửa trực tiếp trên landing mẫu

Trang cảm ơn hiện đang hiển thị literal `::name::`. Với LadiPage, hãy bật Auto Funnel ở form nguồn và dùng đúng biến theo `Tên lấy dữ liệu`, ví dụ `{{name}}`. Điều này cũng là dấu hiệu nên kiểm tra lại việc chuyển dữ liệu/reference từ form chính sang trang cảm ơn.
