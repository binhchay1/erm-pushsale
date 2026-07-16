# V20 — Customer Phone Ownership

## Vấn đề xử lý

V19 đã sửa duplicate scope theo đúng nghiệp vụ mới: cùng số điện thoại ở hai kết nối landing khác nhau vẫn tạo hai đơn riêng để báo cáo Marketing/nguồn/doanh thu không bị mất. Tuy nhiên nếu chỉ dừng ở đó thì có thể phát sinh lỗi vận hành: hai đơn cùng số bị chia cho hai Sale khác nhau và cả hai cùng gọi một khách.

V20 tách rõ hai khái niệm:

- **Duplicate reporting:** quyết định có tạo lead/order mới hay không.
- **Phone ownership:** quyết định Sale nào được phép tác nghiệp với số điện thoại đó.

## Quy tắc mới

```text
Cùng SĐT + cùng Landing Connection
→ duplicate lead
→ không tạo đơn thứ hai
→ đưa vào review

Cùng SĐT + khác Landing Connection
→ vẫn tạo đơn mới để giữ đúng nguồn/doanh thu
→ KHÔNG chia cho Sale khác
→ tự chuyển về Sale đang sở hữu SĐT đó
```

Nếu chưa có Sale sở hữu SĐT, hệ thống chia số theo cấu hình landing như cũ. Nếu SĐT đã có Sale sở hữu, đơn mới luôn về đúng Sale đó, kể cả landing connection thứ hai có danh sách Sale riêng.

## Bảng mới

`customer_phone_locks` lưu một bản ghi ownership theo tenant + số điện thoại:

- `company_id`
- `phone_key`
- `owner_sale_user_id`
- `active_order_id`
- `status`
- `lock_reason`
- `acquired_at`
- `last_activity_at`
- `expires_at`
- `released_at`
- `meta`

Mặc định lock sống 30 ngày, cấu hình bằng:

```dotenv
CUSTOMER_PHONE_LOCK_ACTIVE_DAYS=30
```

## Trạng thái conflict

Khi hệ thống đáng lẽ chia cho Sale B nhưng phone lock bắt buộc chuyển về Sale A, đơn và lead được đánh dấu:

- `orders.phone_lock_conflict = true`
- `orders.phone_lock_note`
- `lead_ingestions.phone_lock_conflict = true`
- `lead_ingestions.phone_lock_owner_user_id = Sale A`

Đây không phải lỗi dữ liệu. Nó là dấu audit để admin biết hệ thống đã tránh được việc hai Sale gọi cùng một khách.

## Luồng tự động

```text
Lead mới về
→ kiểm duplicate theo Landing Connection
→ nếu tạo order mới, kiểm tra phone lock
→ có owner thì dùng owner Sale
→ không có owner thì chia theo round_robin/priority/manual
→ tạo order
→ cập nhật customer_phone_locks
→ Sale workspace chỉ báo cho một Sale
```

## Chia tay thủ công

Admin/Sale Lead chọn nhầm Sale khác cho SĐT đã có owner thì hệ thống vẫn ưu tiên owner hiện tại. Như vậy thao tác tay không phá vỡ nguyên tắc một khách chỉ có một Sale tác nghiệp tại một thời điểm.

## Pancake

Pancake conversation vẫn match theo conversation/order trước, fallback theo SĐT. Vì V20 đảm bảo các order cùng SĐT active đều cùng Sale owner nên chat Pancake không còn bị rơi vào hai workspace khác nhau.

## Báo cáo

Báo cáo vẫn giữ nguyên:

- hai landing connection khác nhau vẫn có hai order;
- doanh thu, sản phẩm, upsale, giá vốn và kho tính theo từng order;
- contact/duplicate theo kết nối landing không bị gộp sai toàn hệ thống;
- phone lock chỉ ảnh hưởng phân công tác nghiệp, không làm mất số liệu nguồn.
