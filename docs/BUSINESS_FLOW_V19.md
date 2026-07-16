# ERM Pushsale V19 — End-to-end business flow contract

## Mục tiêu

V19 khóa lại luồng vận hành từ đầu đến cuối theo cách gần Pushsale nhất nhưng chặt hơn ở các điểm Pushsale gốc còn dễ sai số: kết nối landing, upsale, trùng số, Pancake, kho, giao vận và báo cáo tài chính.

Luồng chuẩn:

1. Admin công ty tạo nhân viên và phân vai: Admin, Marketing, Sale, Kho, Kế toán.
2. Kho hoặc Admin tạo sản phẩm/gói sản phẩm, giá bán, giá vốn và tồn kho.
3. Marketing tạo Kết nối Landing gồm nguồn chính, nguồn upsale, sản phẩm/gói mapping, ngân sách và danh sách Sale nhận số.
4. Admin hoặc Marketing Lead duyệt kết nối.
5. Landing/Ads/Ladipage/Facebook/Youtube gửi form về endpoint riêng của từng nguồn.
6. Lead chính tạo đơn ngay, chia Sale ngay nếu cấu hình cho phép.
7. Upsale cùng `ps_flow` hoặc cùng phiên trong 90 giây được cộng vào đúng đơn, không chia Sale lần hai.
8. Sale chốt bằng workspace hoặc Pancake Extension.
9. Kho chọn hãng vận chuyển, tạo vận đơn, xuất kho idempotent.
10. Webhook vận chuyển cập nhật trạng thái, COD, phí giao, phí hoàn và nhập hoàn.
11. Báo cáo đọc từ facts/snapshot V18, ngày hiện tại vẫn live nhưng không quét lịch sử quá nặng.

## Quy tắc trùng số mới

Quy tắc cũ kiểu "trùng số toàn hệ thống" làm sai business khi một khách đi qua hai landing khác nhau. V19 đổi thành:

- Cùng số điện thoại + cùng Kết nối Landing trong cửa sổ duplicate: là lead trùng, không tạo đơn thứ hai, không tính contact mới, đưa vào ngoại lệ review.
- Cùng số điện thoại + khác Kết nối Landing: tạo đơn mới độc lập, vẫn đánh dấu khách cũ ở cấp đơn để báo cáo biết đây không phải khách mới hoàn toàn.
- Cùng số điện thoại + cùng nguồn marketing không phải Landing Connection: duplicate chỉ trong đúng nguồn đó, không chặn nguồn khác.
- Lead nhập tay không có nguồn vẫn giữ cảnh báo duplicate theo số điện thoại để vận hành kiểm soát.

Các packet upsale/follow-up không bao giờ làm tăng contact, kể cả khi bị đưa vào review.

## Quy tắc upsale

- Landing chính trả `ps_flow` về response JSON và tự gắn vào URL redirect.
- Trang upsale phải gửi lại `ps_flow`, `saleops_session`, `session_id`, hoặc ít nhất số điện thoại trong cửa sổ 90 giây.
- `ps_flow` thuộc kết nối khác bị từ chối `409`.
- Upsale đến trong cửa sổ hợp lệ cộng item vào order hiện có.
- Upsale đến muộn hoặc sau khi Sale đã thao tác chuyển thành packet cần review; không tự tạo đơn mới.
- Retry cùng source + submission id không cộng item hai lần.
- Submission id giống nhau ở hai source khác nhau không xung đột vì đã namespace theo connection/source.

## Quy tắc Pancake

Pancake được xem là lớp chat/chốt đơn song song với workspace SaleOps:

- Extension có actor đăng nhập SaleOps thì ưu tiên gán về chính actor hoặc Sale được actor có quyền chọn.
- Webhook không có actor thì ưu tiên mapping Pancake user → SaleOps user.
- Nếu đã có chủ hội thoại cũ, giữ nguyên Sale owner.
- Webhook tin nhắn Pancake có `conversation_id` nhưng chưa có order sẽ fallback match bằng số điện thoại trong cùng company.
- Khi match được order, hệ thống tạo `PancakeSyncRecord` loại `conversation` để các lần mở chat sau lấy đúng conversation/page.
- Tin nhắn Pancake lưu snapshot vào `pancake_customer_messages`, đồng bộ realtime vào modal Chat khách hàng.

Điểm quan trọng: một đơn từ Landing vẫn có thể được Sale nhắn/chốt qua Pancake nếu webhook/tin nhắn Pancake có cùng số điện thoại và cùng tenant. Hệ thống không cần người dùng copy tay mã conversation vào đơn.

## Quy tắc sản phẩm và tiền

- Product, combo, upsale, giá bán và giá vốn lấy từ backend mapping của Kết nối Landing.
- Client/Landing không được quyết định giá, chiết khấu, tổng tiền hoặc phí ship.
- Mọi field tiền lưu dạng integer VND.
- Doanh thu Sale, Marketing, Kho, Admin Dashboard và CEO Dashboard dùng cùng `order_items` và revenue classifier.
- Upsale được cộng vào doanh số và tồn kho nhưng không tăng contact.

## Quy tắc kho và giao vận

- Đơn mới/chưa chốt không trừ kho.
- Sale chốt xong chuyển kho, kho chọn hãng hoặc dùng hãng mặc định.
- Tạo vận đơn thành công mới xuất kho bằng `inventory_movement` idempotent.
- Webhook giao vận cập nhật trạng thái giao, COD, phí giao, phí hoàn, phí COD, bồi thường.
- Đơn hoàn chỉ cộng lại phần hàng thực nhận và còn bán được.
- COD/phí vận chuyển đi vào dashboard tài chính và báo cáo đối soát, không nhập tay lại.

## Audit vận hành

V19 thêm command:

```bash
php artisan audit:business-flow
php artisan audit:business-flow --company=1
php artisan audit:business-flow --json
```

Command kiểm tra:

- Có đủ user active theo vai trò chính.
- Có sản phẩm active, giá bán, giá vốn.
- Kết nối landing có nguồn chính, mapping sản phẩm/gói, ngân sách, danh sách Sale nếu chia tự động.
- Trạng thái duyệt của `landing_connections` và `marketing_sources` không bị lệch.
- Cấu hình giao vận active và cảnh báo nếu thiếu webhook secret/HMAC.

Command chỉ audit, không tự sửa dữ liệu.

## Các case bắt buộc giữ đúng

| Case | Kết quả |
| --- | --- |
| Khách submit landing chính rồi upsale cùng `ps_flow` | 1 order, 1 Sale, nhiều order_items |
| Retry cùng source/submission_id | Không cộng hàng lần hai |
| Khách submit lại cùng kết nối landing bằng submission khác | Lead duplicate review, không tạo order mới |
| Khách submit hai kết nối landing khác nhau | 2 order độc lập, 2 contact countable |
| Upsale dùng `ps_flow` của kết nối khác | 409 |
| Upsale đến trước landing chính | Giữ pending, retry đến hết hold window |
| Upsale đến muộn | Review exception, không chia Sale lần hai |
| Sale thao tác trước khi upsale đến | Khóa hold, upsale muộn vào review |
| Pancake message webhook cùng số điện thoại đơn Landing | Tự link conversation vào order |
| Tạo vận đơn retry | Không trừ kho hai lần |
| Đơn hoàn nhận thiếu/hỏng | Chỉ cộng lại số lượng restock hợp lệ |

