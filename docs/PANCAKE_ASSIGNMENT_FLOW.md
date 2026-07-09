# Pancake assignment flow

## Mục tiêu

Luồng Pancake không được dựa vào webhook để đoán sale nội bộ một cách mơ hồ. Pancake không biết `users.id` của SaleOps, nên hệ thống dùng thứ tự ưu tiên sau để tránh chia sai số:

1. **Extension authenticated user**: sale đăng nhập extension bằng token SaleOps. Nếu là role `sales`, đơn mặc định về chính sale đó.
2. **Selected sale**: admin, sales cấp trưởng nhóm/trưởng phòng, hoặc user có quyền `pancake:full` được chỉ định sale nhận số.
3. **Pancake user mapping**: webhook/polling có `pancake_user_id/email` thì map qua `pancake_user_mappings`.
4. **Existing conversation owner**: nếu conversation/phone đã từng có đơn, tiếp tục giữ sale đang phụ trách.
5. **Auto routing / pending pool**: không xác định chắc sale thì dùng rule chia số hiện có hoặc đưa về pool.

## Extension

Endpoint:

```http
POST /api/v1/pancake/extension/orders
Authorization: Bearer <SaleOps Sanctum token>
```

Payload có thể truyền:

```json
{
  "pancake_order_id": "123",
  "conversation_id": "abc",
  "customer_phone": "0987654321",
  "saleops": {
    "assignment_mode": "self"
  }
}
```

Hoặc leader/admin chỉ định sale:

```json
{
  "saleops": {
    "assignment_mode": "selected_sale",
    "selected_sale_user_id": 15
  }
}
```

Backend luôn kiểm tra quyền. Sale thường không thể tự gán đơn cho người khác bằng cách sửa payload.

## Webhook / polling

Webhook Pancake không có actor SaleOps nên không được mặc định tin là “sale vừa chat”. Nếu payload có `assignee.id`, `assignee.email`, `creator.id`, `pancake_user_id` hoặc `pancake_user_email`, hệ thống sẽ tìm trong bảng `pancake_user_mappings`.

Tạo mapping bằng command:

```bash
php artisan pancake:map-user \
  --company-id=1 \
  --pancake-user-id=98765 \
  --pancake-user-name="Nguyễn Sale Pancake" \
  --sale-email=sale@example.com \
  --shop-id=123456 \
  --page-id=999999
```

Nếu mapping không tồn tại, hệ thống sẽ tìm sale cũ theo `conversation_id` hoặc số điện thoại. Nếu vẫn không có, lead đi vào rule chia số tự động.

## Bảo mật

- Extension API bắt buộc `auth:sanctum` và throttle riêng `extension-intake`.
- User được gọi extension endpoint: admin, sales, hoặc user có `pancake:full`.
- Selected sale luôn được kiểm tra role, company và phạm vi quản lý.
- `shop_id`/`page_id` được đối chiếu với `PANCAKE_SHOP_ID`, `PANCAKE_PAGE_ID`, `PANCAKE_ALLOWED_SHOP_IDS`, `PANCAKE_ALLOWED_PAGE_IDS` nếu có cấu hình.
- Sync record lưu `assignment.mode`, `reason`, actor và Pancake user để audit khi xảy ra tranh chấp.

## Queue

Pancake order webhook/polling chạy queue riêng:

```env
QUEUE_PANCAKE_ORDERS=pancake-orders
```

Chat Pancake vẫn dùng queue riêng:

```env
QUEUE_PANCAKE_CHAT_SYNC=pancake-chat
QUEUE_PANCAKE_CHAT_BROADCASTS=broadcasts-pancake-chat
```

Nhờ vậy đơn, chat, broadcast và webhook lead thường không nghẽn chung một queue.
