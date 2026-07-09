# Tích hợp Pancake POS / Extension

## Mục tiêu nghiệp vụ

Luồng mới hỗ trợ 3 cách nhận dữ liệu từ Pancake:

1. **Webhook Pancake POS → SaleOps**: Pancake bắn đơn/lead realtime vào URL webhook của nền tảng `pancake`.
2. **Chrome Extension trên Pancake → SaleOps**: nhân sự đang trực page/chat mở Pancake, bấm `Đẩy về SaleOps` để tạo lead/đơn nội bộ.
3. **Polling Open API**: lệnh `php artisan pancake:sync-orders` dùng API Key + Shop ID để quét đơn từ Pancake khi cần đối soát/backfill.

Thiết kế đi theo hướng Pushsale cũ: người dùng thao tác trên Pancake, sau đó dữ liệu chuyển về SaleOps để chia số/tác nghiệp/chốt đơn.

## Luồng chia số hỗ trợ

Luồng extension hiện hỗ trợ 3 cách chia số giống cách vận hành Pancake extension phổ biến:

1. **Chia về chính mình**: tài khoản sales đăng nhập extension → đơn được gán trực tiếp cho sales đó.
2. **Chỉ định người nhận số**: payload truyền `sale_user_id`, `sale_email`, `assignee.sale_user_id` hoặc `assignee.email` → hệ thống cố gắng gán cho sale tương ứng.
3. **Chia theo rule mặc định**: không truyền sale → lead đi qua `LeadRoutingService`/cấu hình chia số hiện có.


## Cấu hình trên SaleOps

Vào **Kết nối nền tảng → Pancake POS / Extension** và nhập:

- `Shop ID Pancake POS`: lấy trên URL Pancake dạng `https://pos.pages.fm/shop/{SHOP_ID}/...`.
- `API Key Pancake POS`: tạo trong Pancake POS phần cấu hình Webhook/API.
- `Token riêng cho Extension`: tuỳ chọn, dùng nếu muốn kiểm tra thêm ở webhook không token.
- `ID nguồn mặc định`: nếu muốn tất cả lead Pancake đổ vào một nguồn marketing cố định.
- `ID kho mặc định`, `Đơn vị giao mặc định`: dành cho mở rộng xuất kho/vận chuyển sau này.

Sau khi lưu, bật `Bật nhận webhook`.

## URL webhook Pancake

Copy URL webhook hiển thị trong card Pancake, dạng:

```txt
https://your-domain.com/api/v1/webhooks/pancake/{webhook_token}
```

Dán URL này vào Pancake POS phần Webhook/API. URL đã chứa token tenant riêng nên đủ dùng cho nền tảng không ký HMAC. Nếu Pancake gửi thêm header ký, driver vẫn hỗ trợ các header:

- `X-Pancake-Signature`
- `X-Hub-Signature-256`
- `X-Webhook-Signature`
- `X-SaleOps-Signature`

## API cho Chrome Extension

Extension gọi:

```http
POST /api/v1/pancake/extension/orders
Authorization: Bearer <saleops_api_token>
Content-Type: application/json
```

Payload tối thiểu:

```json
{
  "pancake_order_id": "123456",
  "shop_id": "6036602",
  "page_id": "fb_page_id",
  "page_name": "Fanpage A",
  "conversation_id": "conversation-id",
  "customer_name": "Nguyễn Văn A",
  "customer_phone": "0912345678",
  "shipping_address": "Hà Nội",
  "message": "Khách hỏi combo 2 sản phẩm",
  "items": [
    { "product_name": "Sản phẩm A", "quantity": 1, "unit_price": 199000 }
  ]
}
```

Quyền API:

- Sales được tạo đơn gán cho chính sale đang đăng nhập.
- Admin hoặc tài khoản có `leads:full` / `telesale:full` có thể gửi payload.
- Nếu payload có `sale_user_id` hoặc `sale_email`, hệ thống sẽ cố gắng gán lead cho sale tương ứng.
- Nếu không có sale, hệ thống đi qua luồng chia số mặc định.

## Cài Chrome Extension demo

Thư mục extension nằm tại:

```txt
extensions/pancake-saleops
```

Cách cài:

1. Mở Chrome → `chrome://extensions`.
2. Bật Developer mode.
3. Chọn `Load unpacked`.
4. Chọn thư mục `extensions/pancake-saleops`.
5. Mở Options của extension, nhập:
   - Base URL SaleOps, ví dụ `https://erm.example.com`.
   - Bearer token lấy từ API `/api/v1/auth/token`.
6. Mở Pancake POS, mở chi tiết hội thoại/đơn, bấm nút `Đẩy về SaleOps`.

Extension demo đọc dữ liệu từ DOM Pancake bằng heuristic. Request API được gửi từ background service worker để tránh lỗi CORS của content script; trong production nên thu hẹp `host_permissions` về domain SaleOps thật thay vì dùng wildcard. Khi triển khai production nên map trực tiếp state/API nội bộ của Pancake nếu có thể lấy được object order/conversation trong page runtime.

## Đồng bộ bằng command

```bash
php artisan pancake:sync-orders --company-id=1 --search=0912345678 --limit=20
```

Command gọi:

```txt
GET https://pos.pages.fm/api/v1/shops/{SHOP_ID}/orders?api_key={API_KEY}&search=...
```

và import từng order về SaleOps.

## Bảng mới

```txt
pancake_sync_records
```

Bảng này dùng để giữ mapping idempotent giữa Pancake và SaleOps:

- `external_type`: order/lead/conversation/customer.
- `external_id`: ID phía Pancake.
- `lead_ingestion_id`: lead nội bộ.
- `order_id`: đơn nội bộ.
- `payload`: raw packet để debug.
- `metadata`: actor, conversation_id, customer_id.

## File chính

- `app/Integrations/Pancake/PancakeLeadDriver.php`
- `app/Services/Pancake/PancakeOrderImportService.php`
- `app/Services/Pancake/PancakeApiClient.php`
- `app/Http/Controllers/Api/V1/Pancake/PancakeExtensionController.php`
- `database/migrations/2026_07_09_000000_create_pancake_sync_records_table.php`
- `extensions/pancake-saleops/*`
