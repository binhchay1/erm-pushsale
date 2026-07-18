# V55 - Sale Workspace Parity

## Mục tiêu
Chuẩn hóa màn Sale tác nghiệp theo UI/UX Pushsale nhưng vẫn giữ kiến trúc ERM: route/service/controller của dự án, component React tách theo trách nhiệm, CSS dùng lại contract chung thay vì vá từng bảng.

## Source Pushsale đã đối chiếu
- Nút `tao-don-fixed` của Pushsale là nút tròn fixed 55x55 ở góc trái dưới.
- Bảng chính dùng `table-multi-select table-sale` với các cột: mã đơn, nguồn dữ liệu, sale/ngày nhận data, khách hàng/sđt, tin nhắn, TN cần, kết quả, TN tiếp, sản phẩm, tiền, trạng thái giao hàng.
- Cột nguồn dữ liệu mở link landing bằng `target="_blank"`.
- Cột Sale/Ngày nhận data có action xóa data.
- Click số điện thoại mở modal danh sách đơn cùng số/trùng số dạng popup rộng 90%.
- Cột sản phẩm/tiền dùng cấu trúc nhiều dòng, nhưng visual không được lộ border con.

## Thay đổi chính
1. `SaleWorkspaceTable.jsx`
   - ProductLines chuyển sang `.ps-split-stack` / `.ps-split-row` thay vì nested table.
   - CSS shared xử lý dotted separator, không còn border ô con.
   - Giữ link source landing từ `sourceUrl`.
   - Nút xóa data hiện theo `canDeleteData`.

2. `SaleOperationDialogs.jsx`
   - `DuplicatePhoneOrdersDialog` làm lại theo popup danh sách đơn cùng số điện thoại.
   - Có filter `Đã chốt đơn`, table rộng, copy địa chỉ.

3. `SaleOperationPolicy.php`
   - `canDeleteData` cho phép đơn thủ công/manual chưa có tracking vẫn hiện nút xóa data.

4. `Workspace.jsx`
   - Nút tạo đơn dùng `tao-don-fixed ps-create-order-fab` để ăn chung rule nút tròn.

5. `pushsale.css`
   - Thêm block `V55 - Sale workspace parity + shared table/action cleanup`.
   - Chuẩn hóa filter, tab trạng thái, table height, colgroup, stack cell, TN cần hover/focus không làm giãn dòng, nút tạo đơn tròn, modal tạo đơn và modal trùng số.

## Lưu ý kiến trúc
- Không tạo CSS riêng cho từng bảng nếu pattern giống nhau.
- Các cột dùng chung giữa Hồ sơ khách hàng / Sale tác nghiệp / Kho tác nghiệp phải ưu tiên shared class: `.ps-split-stack`, `.ps-split-row`, `.ps-money-stack`, `.ps-product-stack`, `.tao-don-fixed`, `.ps-create-order-fab`.
- Không quay lại nested table có border lộ ra trong cell nhiều dòng.
