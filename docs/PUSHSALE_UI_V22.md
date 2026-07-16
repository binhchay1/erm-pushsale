# V22 — Pushsale UI rebuild: Phân bổ data & Cấu hình giao hàng

## 1. Phân bổ data thay thế Nhật ký lead

Màn `/admin/leads`, `/marketing/leads` và `/allocator/workspace` không còn render nhật ký packet lead. Trang mới dùng giao diện Pushsale `redistribute-contact`:

- Bộ lọc khách cũ, phạm vi data, trạng thái tác nghiệp và khoảng ngày.
- Cảnh báo giới hạn mỗi lần phân bổ tối đa 5.000 bản ghi.
- Bảng trái: số contact chờ phân bổ theo sản phẩm.
- Bảng phải: danh sách Sale được phân bổ, trạng thái nhận data, số contact trong ngày và số đơn đang dùng.
- Các checkbox đúng luồng Pushsale: xóa lịch sử tác nghiệp, xóa tin nhắn nội bộ, ẩn/bỏ qua Sale tắt nhận data hoặc bị khóa.

Luồng backend mới nằm ở `DataDistributionService` và `DataDistributionController`.

## 2. Nhật ký lead về

Menu Nhật ký lead đã được bỏ khỏi giao diện. Các route tạo lead thủ công, import lead, review packet và allocate cũ vẫn được giữ làm API nghiệp vụ/hậu trường để không làm gãy webhook, import excel, chia số thủ công từ màn khác và review exception. Người dùng không thao tác qua màn Nhật ký lead cũ nữa.

## 3. Cấu hình giao hàng

Màn `/admin/shipping-partners` bỏ layout cũ và chuyển sang layout Pushsale:

- Cột trái danh sách hãng: VN Post, Viettel Post, GHTK, GHN, J&T, EMS, SuperShip, Best, BoxMe, Chim Cắt, Ship60, HolaShip, AhaMove, NinjaVan, SPX Express, Đối tác trung gian.
- Mỗi hãng có form riêng đúng cấu trúc field trong template/screenshot.
- Cấu hình vẫn lưu vào `shipping_partner_connections`, dùng lại adapter hiện có cho tạo vận đơn, tracking, webhook, COD và đối soát.
- Đối tác trung gian giữ vai trò multi-carrier API để dùng một bên thứ ba đã kết nối sẵn nhiều hãng vận chuyển.

## 4. Menu và shell CSS

`pushsale-system-v22.css` là lớp cuối cho giao diện nội bộ:

- Font Arial toàn bộ ERM nội bộ.
- Header cao 50px, màu `#007bff`.
- Hamburger 42px giống AdminLTE/Pushsale.
- Nhóm menu lớn in đậm; menu cấp con 12px, nhẹ hơn.
- CSS public/login không bị ảnh hưởng.
