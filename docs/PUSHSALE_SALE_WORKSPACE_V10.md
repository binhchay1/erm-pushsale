# Pushsale Sale tác nghiệp V10

## Phạm vi

Trang `Sale tác nghiệp` được dựng lại từ bộ `template-second.zip`:

- `html.txt` / `html.png`: màn hình chính.
- `tạo đơn.txt` / `tạo đơn.png`: modal tạo đơn.
- `cập nhật đơn.png`: trạng thái modal cập nhật đơn.
- `cập nhật ngày muốn giao hàng.txt` / `.png`: modal ngày giao mong muốn.
- `lịch sử tác nghiệp.png`: modal lịch sử tác nghiệp.

HTML nguồn chỉ dùng làm chuẩn DOM/CSS/hành vi. Dữ liệu hiển thị và các thao tác đều sử dụng model/service hiện có của ERM.

## Route và backend

Trang được phục vụ bởi `OperationController` và các service tác nghiệp hiện có. Các action bổ sung:

- `POST .../orders/{order}/call`: ghi nhận cuộc gọi.
- `POST .../orders/{order}/operation-status`: cập nhật kết quả tác nghiệp.
- `PATCH .../orders/{order}/operation-note`: lưu `TN cần`, tối đa 500 ký tự.
- `PATCH .../orders/{order}/desired-delivery-date`: cập nhật ngày muốn nhận hàng / tác nghiệp tiếp.
- `POST .../orders/{order}/details`: cập nhật thông tin đơn.
- `POST .../orders/{order}/close`: chốt đơn và cấp mã đơn.
- `DELETE .../orders/{order}`: xóa data chưa chốt, chưa phát sinh giao vận.
- `POST .../orders/bulk-close`: chốt nhiều đơn đã chọn.

Các action có cả scope Admin và Sales. Backend vẫn kiểm tra tenant, role, chủ sở hữu data, trạng thái đơn và phát sinh giao vận; không dựa riêng vào việc ẩn/hiện nút trên frontend.

## Dữ liệu thật

Bảng lấy dữ liệu từ các quan hệ thật:

- `orders`
- `order_items`
- `marketing_sources`
- `users`, `teams`
- `warehouses`
- shipment / tracking data
- operation history
- supplement/upsale packets

Không render lại các dòng dữ liệu được capture trong HTML mẫu.

## Mã đơn chỉ sinh khi chốt

- Đơn mới được tạo với `order_code = NULL`.
- Cột mã đơn để trống khi đơn đang tác nghiệp.
- `OrderClosingService` dùng `OrderCodeGenerator` cấp mã trong transaction khi chốt thành công.
- Mã có dạng `PS` + ID 11 chữ số + `PS`.
- Presenter không công bố mã của dữ liệu cũ nếu bản ghi chưa có `closed_at`.

Migration:

`database/migrations/2026_07_13_020000_defer_order_code_and_add_sale_operation_note.php`

Migration đồng thời thêm `sale_operation_note VARCHAR(500)`.

## Danh mục và luồng tác nghiệp

`SaleOperationConfigurationService` đọc cấu hình thật từ:

- Menu `1.8.1 Danh mục tác nghiệp` (`operation_categories`).
- Menu `1.8.2 Thiết lập luồng tác nghiệp` (`operation_workflows`).

Nếu đơn vị chưa cấu hình, quy trình mặc định là:

1. Gọi lần 1
2. Gọi lần 2
3. Gọi lần 3
4. Gọi lần 4
5. Gọi lần 5
6. Gọi lần 6
7. Chăm sóc lần 1
8. Chăm sóc lần 2
9. Chăm sóc lần 3
10. Bỏ qua

Đơn mới mặc định ở `Gọi lần 1`. Tên/thời lượng của stage lấy từ cấu hình đơn vị khi có.

## TN cần

- Normal: textarea cao 48 px, nằm trong ô bảng.
- Hover: mở rộng thành editor nổi để đọc/nhập nội dung dài.
- Click/focus: ghim editor ở trạng thái mở.
- Nút thu gọn hoặc phím Esc: bỏ ghim.
- Tối đa 500 ký tự ở cả frontend và backend.
- `Ctrl + Enter`: lưu nhanh.
- Nội dung lưu vào `orders.sale_operation_note`, tách khỏi tin nhắn của khách.

## Các cột và action

Bảng giữ cấu trúc 14 cột của ảnh Pushsale:

1. Checkbox / STT
2. Mã đơn
3. Nguồn dữ liệu / Ngày data về
4. Sale / Ngày nhận data
5. Họ tên / Số điện thoại / Ngày muốn nhận hàng
6. Tin nhắn
7. TN cần
8. Kết quả
9. TN tiếp
10. Sau / Còn lại
11. Sản phẩm - Số lượng - Đơn giá
12. Thành tiền / CK / VAT / Phí VC / Tổng tiền
13. Đặt cọc
14. Trạng thái giao hàng / Ngày muốn nhận hàng

Action được nối với dữ liệu thật:

- Xem lịch sử xem thông tin số.
- Cập nhật đơn.
- Gọi điện / ghi nhận cuộc gọi.
- Danh sách các đơn cùng số điện thoại.
- Tin nhắn nội bộ và chat khách hàng.
- Lịch sử mua hàng.
- Lịch sử tác nghiệp sale.
- Lịch sử kế toán.
- Cập nhật tác nghiệp tiếp / ngày muốn nhận hàng.
- Xóa data an toàn.
- Chốt đơn nhiều.
- Tạo đơn mới.

## Modal

### Tạo/Cập nhật đơn

Modal dùng chung component nhưng có chế độ tạo và sửa. Dữ liệu gồm khách hàng, người nhận khác, địa chỉ, nguồn, kho, vận chuyển, sản phẩm/combo, số lượng, đơn giá, chiết khấu, VAT, phí giao hàng, đặt cọc và tổng tiền.

Khi chọn kết quả `Chốt đơn`, modal cập nhật đơn được mở ở chế độ chốt; mã đơn chỉ được sinh sau request chốt thành công.

### Lịch sử tác nghiệp

- Header, màu từng loại dòng và bố cục theo ảnh nguồn.
- Dữ liệu thật từ operation history và activity logs.
- Có ngữ cảnh Sale hoặc Kế toán.
- Hiển thị người thao tác, thời gian, hành động, trạng thái trước/sau, ghi chú và snapshot dữ liệu liên quan.

### Ngày muốn nhận hàng / tác nghiệp tiếp

Modal riêng, cập nhật ngày giờ thật và reload đúng phần dữ liệu Inertia.

### Danh sách trùng số

Mở danh sách mọi đơn có cùng số điện thoại thông qua endpoint lịch sử mua hàng; không dùng dữ liệu mẫu.

## Upsale

Giữ nguyên toàn bộ business hiện có:

- hold window 90 giây;
- idempotency theo submission;
- gộp khách hàng/đơn;
- orphan và late upsale chuyển review;
- khóa đơn sau sale action;
- đơn bổ sung;
- audit packet;
- hiển thị tag UPSALE, trạng thái đang chờ và packet chờ duyệt trong giao diện mới.

## Phân trang

- Sale tác nghiệp dùng phân trang backend, lựa chọn 10/20/50/100 dòng, trang đầu/trước/sau/cuối.
- `BusinessPage` dùng bộ phân trang chung cùng lựa chọn số dòng cho mọi trang bảng template.
- Trang hồ sơ khách hàng, báo cáo, nhân viên, đội nhóm, sản phẩm, kho, tồn kho, lịch sử và đối soát giữ paginator riêng nếu đã có.
- Dashboard chỉ có KPI/biểu đồ và không có danh sách dòng sẽ không chèn phân trang giả.

## Frontend

Các file chính:

- `resources/js/pages/Sales/Workspace.jsx`
- `resources/js/components/operations/pushsale/SaleWorkspaceFilters.jsx`
- `SaleWorkspaceTabs.jsx`
- `SaleWorkspaceTable.jsx`
- `SaleOrderDialog.jsx`
- `SaleOperationDialogs.jsx`
- `PushsalePagination.jsx`
- `resources/css/pushsale-sale-workspace.css`

CSS chung của AdminLTE 2/Bootstrap 3 vẫn được nạp bởi shell Pushsale. CSS trang nằm cuối cascade để giữ đúng giao diện nguồn mà không ảnh hưởng login/public shell.
