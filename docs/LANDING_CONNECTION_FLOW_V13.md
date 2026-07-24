# V13 — Kết nối Landing, gộp Upsale và luồng bán hàng khép kín

## 1. Quyết định nghiệp vụ

Luồng Marketing **không còn bắt đầu bằng thao tác tạo campaign**. Điểm khởi tạo duy nhất là menu **2.4.1 — Kết nối landing**.

Một `landing_connection` là hồ sơ cấu hình hoàn chỉnh cho toàn bộ phễu:

- marketer phụ trách;
- đúng một Landing chính;
- không giới hạn các trang upsale;
- có thể khai báo trang cảm ơn/đích cuối;
- sản phẩm hoặc gói sản phẩm được lấy từ kho backend;
- field/giá trị của form dùng để chọn gói;
- danh sách Sale được phép nhận data;
- cơ chế chia số;
- trạng thái hoạt động và phê duyệt.

`marketing_sources` vẫn được giữ như bản ghi tương thích cho báo cáo hiện hữu. Nó **không còn là đối tượng người dùng phải tạo trước** và không phải nguồn cấu hình runtime của Landing.

## 2. Mô hình dữ liệu

### `landing_connections`

Định danh một phễu hoàn chỉnh. Mỗi kết nối có `public_token` riêng, trạng thái duyệt, trạng thái hoạt động, marketer và phương thức chia số.

### `landing_connection_sources`

Mỗi URL trong phễu là một nguồn riêng:

| `source_type` | Ý nghĩa | Nhận form |
|---|---|---|
| `main` | Landing đầu tiên | Có |
| `upsell` | Trang bán thêm sau submit | Có |
| `thank_you` | Trang đích cuối/chỉ để định danh | Không |

Mỗi source có token và URL submit riêng. Nhờ đó cùng một kết nối vẫn truy vết chính xác packet đến từ Landing chính hay trang upsale nào.

### `landing_connection_products`

Map sản phẩm backend vào từng source:

- không khai báo `external_field`: dòng hàng cố định, luôn được thêm;
- có `external_field` + `external_value`: chỉ thêm khi form gửi giá trị khớp;
- `is_default`: phương án dự phòng khi form không gửi/không khớp lựa chọn;
- `quantity` và `unit_price_override`: giá trị do backend quyết định.

Giá, số lượng, chiết khấu và danh sách item do client gửi lên **không được tin cậy**. Endpoint luôn dựng lại order item từ cấu hình backend.

Mapping lựa chọn gói dùng so khớp **chính xác sau khi chuẩn hóa** để tránh nhầm `gói-1` với `gói-10`. Có thể khai báo nhiều giá trị hợp lệ bằng dấu `|`. Mapper đọc được field phẳng, object `fields` và danh sách `{name, value}` thường gặp ở landing builder.

### `landing_connection_sales`

Danh sách Sale nhận data của riêng kết nối. Hỗ trợ:

- `round_robin`: luân phiên trong danh sách;
- `priority`: ưu tiên theo thứ tự đã cấu hình;
- `inherit`: dùng chế độ auto/manual chung nhưng vẫn giới hạn vào danh sách của kết nối nếu có;
- `manual`: data vào pool, không tự gán.

### Truy vết xuyên suốt

Các bảng `landing_sessions`, `lead_ingestions`, `orders` có thêm:

- `landing_connection_id`;
- `landing_connection_source_id`.

Order giữ source chính; các packet upsale giữ source thực tế của từng request. Vì vậy báo cáo có thể tổng hợp theo kết nối nhưng audit vẫn xuống được từng trang.

## 3. API nhận form

```text
POST /api/v1/landing-connections/{connectionToken}/sources/{sourceToken}/submit
```

Không cần SDK hoặc đoạn tracking JavaScript do ERM cung cấp. Landing chỉ cần submit form trực tiếp vào URL trên.

Ví dụ Landing chính:

```html
<form method="POST" action="https://ERM-DOMAIN/api/v1/landing-connections/CONNECTION_TOKEN/sources/MAIN_SOURCE_TOKEN/submit">
    <input name="name" placeholder="Họ tên">
    <input name="phone" placeholder="Số điện thoại" required>
    <input name="address" placeholder="Địa chỉ">
    <textarea name="message"></textarea>

    <!-- Field lựa chọn gói; backend map giá trị này sang sản phẩm thật -->
    <select name="goi_san_pham">
        <option value="goi-1">Gói 1</option>
        <option value="goi-2">Gói 2</option>
    </select>

    <!-- Khuyến nghị: mã duy nhất do nền tảng landing tạo -->
    <input type="hidden" name="submission_id" value="FORM_UNIQUE_ID">
    <button type="submit">Đặt hàng</button>
</form>
```

Response JSON chỉ trả dữ liệu công khai cần thiết:

```json
{
  "ok": true,
  "flow_token": "opaque-token",
  "status": "processed",
  "requires_review": false,
  "redirect_url": "https://landing.example/upsale?ps_flow=opaque-token&saleops_session=opaque-token"
}
```

ID nội bộ của lead/order không bị lộ ra endpoint công khai.

## 4. Nối Landing chính với Upsale mà không tạo nhầm khách/đơn

Sau submit Landing chính, server redirect sang URL cấu hình và tự gắn:

- `ps_flow`;
- `saleops_session`.

Trang upsale gửi lại một trong các định danh sau, ưu tiên từ trên xuống:

1. `ps_flow`;
2. `saleops_session`;
3. `session_id` / `session_key`;
4. cùng số điện thoại trong cửa sổ 90 giây.

Không bắt buộc nhúng custom JS. Với các landing builder, chỉ cần map query parameter `ps_flow` vào hidden field. Nếu builder không hỗ trợ, form upsale gửi lại số điện thoại là fallback an toàn trong cửa sổ gom.

Ví dụ form upsale:

```html
<form method="POST" action="https://ERM-DOMAIN/api/v1/landing-connections/CONNECTION_TOKEN/sources/UPSELL_SOURCE_TOKEN/submit">
    <input type="hidden" name="ps_flow" value="QUERY_PARAM_PS_FLOW">
    <input type="hidden" name="submission_id" value="UPSELL_FORM_UNIQUE_ID">
    <input type="hidden" name="mua_them" value="ban-chai-8-chiec">
    <button type="submit">Mua thêm</button>
</form>
```

### Quy tắc gộp

- Trong 90 giây và order chưa bị Sale thao tác khóa: item upsale cộng vào đúng order, tổng tiền tính lại ngay, không chia Sale lần hai.
- Retry cùng source + `submission_id`: idempotent, không nhân đôi hàng.
- Cùng `submission_id` nhưng khác source: không xung đột vì server namespace theo connection/source.
- Upsale đến trước Landing chính: giữ packet chờ ghép, không tạo order độc lập.
- Upsale muộn hoặc order đã chốt/khóa: đưa vào hàng review, không tự sửa order đã an toàn.
- Trang `thank_you`: chỉ là đích điều hướng, endpoint trả 405 nếu bị dùng nhầm để submit.

## 5. Luồng tự động phía sau

```text
Marketing tạo Kết nối Landing đầy đủ
    → người có quyền duyệt
    → khách submit Landing chính
    → server xác định sản phẩm/giá từ cấu hình backend
    → chống retry + tạo packet audit
    → tạo order chờ tác nghiệp ngay
    → chia đúng Sale trong danh sách kết nối
    → upsale trong cửa sổ gom tự cộng item và tính lại total
    → Sale chỉ cần gọi, xác nhận thông tin và chốt
    → lúc chốt kiểm tra tồn kho theo toàn bộ order item
    → tạo vận đơn thành công thì trừ tồn kho idempotent và ghi lịch sử biến động
    → trạng thái giao/COD cập nhật báo cáo Marketing, Sale, Kho, Kế toán
```

### Tồn kho

ERM dùng hai mốc để tránh trừ kho sai:

1. **Chốt đơn:** kiểm tra chính xác số lượng cần cho từng `order_item`; thiếu kho thì chặn hoặc yêu cầu xác nhận ngoại lệ.
2. **Tạo vận đơn thành công:** trừ tồn thực một lần duy nhất qua `inventory_deducted_at` và ghi `warehouse_inventory_movements`.

Không trừ tồn ngay khi khách vừa submit vì đơn vẫn đang ở trạng thái telesale xác nhận. Cách này tránh mất tồn ảo do lead rác/khách không nghe máy.

### Doanh thu

- `subtotal`: tổng `unit_price × quantity` của toàn bộ sản phẩm chính + upsale;
- `total`: subtotal trừ chiết khấu backend;
- `amount_to_collect`: total + phí ship khách trả − tiền cọc;
- báo cáo Marketing kế thừa `marketing_source_id` tương thích;
- `landing_connection_id/source_id` cung cấp drill-down mới theo phễu và từng trang.

## 6. Bảo mật và chống sai thao tác

- token connection/source là capability token ngẫu nhiên, không dùng ID tuần tự;
- chỉ connection đã duyệt + đang hoạt động mới nhận form;
- source `thank_you` không nhận form;
- rate limit `lead-intake` áp dụng cho endpoint;
- sản phẩm, giá, số lượng và chiết khấu client bị loại bỏ;
- payload gốc vẫn được lưu ở inbound event để audit, nhưng không dùng tính tiền;
- mọi query runtime được chuyển vào đúng tenant sau khi resolve token;
- mỗi packet có idempotency key đã namespace;
- order chính không bị source upsale ghi đè.
- `ps_flow` đã tồn tại ở kết nối khác trong cùng tenant bị từ chối 409, không thể nối chéo order;
- source bị gỡ được soft-delete: URL ngừng nhận form nhưng lead/session/order lịch sử vẫn truy ra đúng nguồn cũ.

## 7. Checklist cấu hình bắt buộc

Trước khi bật một kết nối:

1. Có đúng một source `main`.
2. Mọi source nhận form có ít nhất một dòng sản phẩm áp dụng.
3. Có sản phẩm cố định hoặc gói mặc định để tránh form sai value làm mất hàng.
4. URL source/redirect là HTTP(S) hợp lệ.
5. Marketer đúng công ty.
6. Sale được chọn đúng role và đúng công ty.
7. Chọn phương thức chia số.
8. Người có quyền duyệt bật `Đã duyệt`.
9. Gửi một đơn test và một upsale test; kiểm tra một order, một Sale, đúng tổng tiền.

## 8. Các file trọng tâm

- Migration: `database/migrations/2026_07_14_000000_create_landing_connection_flow_tables.php`
- Models: `app/Models/LandingConnection*.php`
- CRUD/config: `LandingConnectionManager`, `Page2_4_1Controller`
- Public endpoint: `LandingConnectionSubmissionController`
- Pricing mapper: `LandingConnectionPayloadMapper`
- Gộp packet: `LeadIngestionService`
- Chia Sale: `LeadRoutingService`
- UI: `resources/js/pages/Pushsale/Pages/Page_2_4_1.jsx`
- CSS: `pushsale-landing-connections.css`, `pushsale-legacy-adminlte-fixes.css`
- Test: `tests/Feature/Leads/LandingConnectionFlowTest.php`
