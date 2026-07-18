# V39 - Pushsale UI system consolidation, table/action cleanup

## Mục tiêu
Sửa đúng các lỗi UI còn lại theo ảnh test staging:
- Bỏ vệt xanh trên đầu trang do `border-top` ở các page chrome/filter toolbar.
- Không vá lẻ từng trang nữa: gom rule chung vào cuối `resources/css/pushsale.css` trong block `V39 Pushsale UI system consolidation`.
- Nới các cột dữ liệu quan trọng để không tạo cảm giác ô con bị đóng khung.
- Chuẩn hóa FAB/action button, bảng, icon thao tác, tab tác nghiệp theo source Pushsale.

## Điểm đã sửa
1. `psfd-toolbar` / `ps-wh-filter` / `ps-sale-filter-shell` / `psm-topbar` / `psr-topbar` bỏ `border-top` để hết vệt xanh đầu trang.
2. Marketing dashboard nới `Tên nguồn dữ liệu` và `Sản phẩm` lên 250px bằng col class, không dựa riêng nth-child.
3. Sale/customer tables nới product/money columns và bỏ border/box-shadow nội bộ ở `tb-in-sp`, `ps-money-cell`.
4. FAB tạo đơn/action dùng chung selector `.tao-don-fixed`, `a.tao-don-fixed`, `button.tao-don-fixed`, `.pushsale-create-order-fab`, `.ps-action-fab`; ép fixed/circle/clip-path để không nhảy khi mở devtools.
5. Tab tác nghiệp sale dùng lại pattern `.dm-tac-nghiep`, `.flag`, `.count`, level colors theo HTML source Pushsale.
6. Warehouse source component thêm selection state + action bar để checkbox đầu bảng phục vụ bulk actions: tạo vận đơn, in đơn/nhãn, cập nhật giao hàng, care kho, nhập hoàn.

## Files touched
- resources/css/pushsale.css
- public/build/assets/pushsale-VZglJWi2.css
- resources/css/pushsale-admin-finance-dashboard.css
- resources/js/components/operations/WarehouseOrderTable.jsx

## Lưu ý
Deploy script `deploy/ssd-deploy.sh` có `npm run build`, nên source JSX sẽ được build lại trên server. Built CSS hiện tại cũng đã được patch để server chưa build vẫn ăn phần CSS chính.
