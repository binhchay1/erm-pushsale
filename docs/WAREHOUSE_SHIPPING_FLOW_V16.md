# V16 — Tác nghiệp kho, giao vận, hàng hoàn và dòng tiền

## 1. Phạm vi

V16 thay thế màn tác nghiệp kho cũ và cấu hình giao vận cũ bằng một luồng thống nhất:

1. Sale chốt đơn và khóa sản phẩm/giá trị đơn.
2. Kho kiểm tra tồn, sửa thông tin giao nhận khi đơn chưa xuất kho, tách đơn nếu cần.
3. Hệ thống tạo vận đơn thủ công hoặc qua API hãng vận chuyển.
4. Khi vận đơn được tạo thành công, tồn kho được xuất đúng một lần.
5. Webhook hãng vận chuyển cập nhật timeline, trạng thái giao hàng, COD và toàn bộ phí.
6. Khi hàng hoàn thực tế về kho, hệ thống tạo biên bản nhận hoàn theo từng sản phẩm; chỉ phần còn bán được mới cộng lại tồn.
7. COD, phí giao, phí hoàn, phí COD, phí bảo hiểm, phụ phí và bồi thường được đưa vào đối soát và báo cáo tài chính.

Mọi số tiền lưu dưới dạng số nguyên VND. Client không được gửi số tiền đã format vào database.

## 2. Tác nghiệp kho

Màn `Thủ kho tác nghiệp` có:

- Bộ lọc ngày, Sale, Marketing, kho, nguồn, sản phẩm, hãng vận chuyển, trạng thái care, trạng thái in, cọc, số lượng sản phẩm và cảnh báo tracking.
- Tab nghiệp vụ: chờ vận đơn, đã đăng đơn, lấy hàng, đang giao, giao thành công, giao một phần, đã đối soát COD, đang hoàn, đã hoàn, đã hủy.
- Tách riêng sản phẩm chính và sản phẩm upsale, nhưng tổng tiền/SL vẫn tính trên toàn bộ order item.
- Các số liệu tiền: giá trị đơn, cọc, COD dự kiến, COD đã thu/đã chuyển, phí giao, phí hoàn, phí COD, phụ phí, bồi thường, chi phí giao vận ròng và dòng tiền ròng.
- Timeline webhook theo từng vận đơn.

Các dialog theo template-seven:

- Cập nhật ngày giao mong muốn.
- Blacklist số điện thoại.
- Cập nhật trạng thái care của kho.
- Cập nhật trạng thái giao hàng và số tiền thu khi giao một phần.
- Sửa thông tin đơn trước khi xuất kho.
- Tách đơn trước khi có mã vận đơn/xuất kho.
- Nhận hàng hoàn theo từng dòng sản phẩm.
- Xem timeline trạng thái hãng vận chuyển.

## 3. Quy tắc tồn kho

### Xuất kho

- Không trừ tồn lúc lead về.
- Không trừ tồn chỉ vì Sale bấm chốt.
- Trừ tồn khi tạo vận đơn/phiếu giao thành công, tức đơn đã chuyển sang khâu xuất giao.
- `inventory_deducted_at` và movement theo order bảo đảm retry không trừ hai lần.
- Không cho sửa/tách sản phẩm sau khi đã xuất kho.

### Nhập hàng hoàn

Webhook trạng thái `returned` chỉ tự nhập kho khi connection bật `auto_restock_return`.

Biên bản hoàn có các lượng:

- expected: dự kiến phải nhận;
- received: thực nhận;
- restock: còn bán được và được cộng tồn;
- damaged: hỏng;
- missing: thiếu/mất;
- inspection: chờ kiểm định.

Retry webhook chỉ cộng phần chênh lệch tăng thêm. Hàng hỏng, thiếu hoặc chờ kiểm định không được cộng tồn bán được.

## 4. Chuẩn hóa trạng thái

Mọi provider được map về enum nội bộ:

- `waiting_waybill`
- `posted`
- `picking_up`
- `delivering`
- `redelivery`
- `partial_delivery`
- `delivered`
- `paid`
- `cannot_pickup`
- `cannot_deliver`
- `returning`
- `returned`
- `cancel_waybill`

Raw status vẫn được giữ nguyên trong `shipping_status_events` để audit.

## 5. Chuẩn hóa webhook

Endpoint:

```text
POST /api/v1/shipping/webhooks/{provider}
```

Xác thực hỗ trợ:

- `X-Webhook-Secret`
- `X-Api-Key`
- query `secret`
- HMAC theo header/algorithm cấu hình của connection

Production không chấp nhận connection không có secret. Local/testing có thể cho phép một connection duy nhất không secret.

Payload có thể dùng tên field riêng của hãng; service sẽ chuẩn hóa các nhóm:

```json
{
  "event_id": "unique-event-id",
  "tracking_number": "TRACK001",
  "order_code": "PS001",
  "status": "returned",
  "event_time": "2026-07-14T10:00:00+07:00",
  "cod_amount": 500000,
  "cod_collected": 500000,
  "cod_remitted": 500000,
  "shipping_fee": 30000,
  "return_fee": 25000,
  "cod_fee": 5000,
  "insurance_fee": 2000,
  "other_fee": 1000,
  "compensation_amount": 0,
  "return_items": []
}
```

Khóa idempotency ưu tiên `event_id/webhook_id/transaction_id`; nếu không có thì hash toàn payload. Không dùng thời điểm nhận hiện tại trong khóa, nên retry payload không có `event_time` vẫn không xử lý hai lần.

## 6. Công thức dòng tiền giao vận

```text
Chi phí giao vận ròng
= phí giao
+ phí hoàn
+ phí COD
+ phí bảo hiểm/phụ phí khác
+ hỗ trợ vận chuyển/COD nội bộ
- tiền bồi thường
```

Chi phí không âm ở báo cáo doanh thu. Tiền bồi thường vượt phí cần được hạch toán tiếp như thu nhập khác nếu doanh nghiệp muốn tách tài khoản kế toán.

```text
Dòng tiền ròng tại kho
= COD đã đối soát + tiền cọc - chi phí giao vận ròng
```

Dòng tiền tại kho có thể âm đối với đơn hoàn/chưa thu COD; giao diện không ép về 0.

## 7. Kết nối hãng vận chuyển

### Adapter chuyên biệt trong source

- Viettel Post
- Giao Hàng Nhanh
- Giao Hàng Tiết Kiệm
- J&T Express
- SPX Express (endpoint/credential merchant do đối tác cấp)
- Thủ công

### Adapter cấu hình chuẩn / trung gian

Các hãng hoặc nền tảng chưa có contract công khai ổn định cho tài khoản hiện tại dùng `GenericCarrier`:

- VNPost, EMS, SuperShip, BEST, HeyU, BoxMe, Chim Cắt, Ship60, HolaShip, AhaMove, Ninja Van;
- Shippo;
- TikTok Shop Logistics, Shopee Logistics;
- `aggregator` cho đối tác trung gian multi-carrier.

Generic adapter gửi payload chuẩn ERM; admin cấu hình base URL, token, auth header/prefix và endpoint create/status/rates/cancel/label. Đây là lớp kỹ thuật đầy đủ để tích hợp, nhưng mỗi hãng chỉ có thể chạy production sau khi có tài khoản, credential, contract API và mẫu payload thực tế từ hãng/đối tác.

## 8. Cấu hình mặc định

Mỗi công ty có:

- `default_shipping_provider`
- `default_shipping_method`

Mỗi provider có cấu hình độc lập theo tenant. Unique cũ theo provider toàn hệ thống đã được đổi thành `(company_id, provider)`; mã vận đơn đối tác cũng unique theo tenant.

Các cờ tự động:

- `auto_create_waybill`
- `auto_restock_return`
- `use_carrier_cod`
- `allow_partial_delivery`
- `insurance_enabled`

Chỉ khi provider đã bật, đủ credential và `auto_create_waybill=true`, sự kiện chốt đơn mới đưa job tạo vận đơn vào queue.

## 9. Queue và triển khai

Các job giao vận tiếp tục chạy queue `shipments`; webhook chạy queue shipping webhook đã có trong Horizon.

Deploy:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan horizon:terminate
```

Sau deploy cần cấu hình secret webhook ở cả ERM và portal của hãng/đối tác, rồi test create/status/webhook trên sandbox hoặc account test trước khi bật tự động tạo vận đơn.
