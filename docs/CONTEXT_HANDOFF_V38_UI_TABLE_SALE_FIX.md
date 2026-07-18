# V38 — UI table/menu/action + Sale workspace stability

## Mục tiêu
Bản này xử lý các lỗi UI còn sót sau V37 theo feedback ngày 18/07/2026:
- Icon hướng dẫn chuyển lên khu header cạnh ngôn ngữ.
- Menu trái bị mất nền / animation mở quá nhanh.
- Nút action nổi phải tròn, fixed viewport, không nhảy khi mở F12.
- Bảng nhiều trang có viền quanh icon action.
- Các cột dữ liệu quan trọng bị bó hẹp.
- Danh sách nhân viên đang hiểu nhầm `Số TK` và cột `#`.
- Sale tác nghiệp vẫn còn rủi ro lỗi count tab `between 0 and 1`.
- Cột `TN cần` cần hiển thị rộng và textarea hover/focus phóng to rõ hơn.

## Thay đổi chính

### Layout / header
- `AppHeader.jsx`: thêm `PageInfoButton` vào `pushsale-header-tools`, đặt trước LanguageToggle.
- Built asset `AppLayout-C6T4YjHa.js`: thêm fallback header help button để bản deploy không build lại vẫn có icon ở header.
- CSS ẩn các nút help cũ nằm trong content nhưng vẫn giữ DOM để header proxy có thể trigger.

### Sidebar/menu
- Khôi phục nền menu `#f7f8fb`, nền submenu trắng, active cấp 2 xanh.
- Khôi phục shadow nhẹ cho sidebar và third-level flyout.
- Làm animation sidebar chậm/mượt hơn: ~0.34–0.38s.
- Làm submenu xổ xuống chậm hơn: ~0.46s max-height.

### Bảng/action buttons
- Override icon actions trong bảng: bỏ border/background/box-shadow quanh các icon sửa/xóa/đổi mật khẩu.
- Tăng padding/line-height cho bảng report và bảng nhân viên.
- Tăng min-width các bảng report/source/marketing.
- Tăng các cột quan trọng: email/phone/source, customer/message/TN cần/product/money/delivery trong Sale tác nghiệp.

### Danh sách nhân viên
- Source `Users/Index.jsx`: `Số TK` đổi sang số bản ghi đang hiển thị trên trang hiện tại (`rows.length`) để tránh hiểu nhầm 24 là 24 dòng đang nhìn thấy.
- Bỏ cột `#` vì đang trùng ý nghĩa với STT nhưng thực chất là ID tài khoản, gây rối giao diện.
- Built asset `Index-Cp25Z0U-.js` đã patch tương ứng.

### Sale tác nghiệp
- `SaleOperationService`: count tab dùng query clone sạch và có try/catch fallback, không để lỗi đếm tab làm chết toàn bộ trang.
- `ReportDateRange`: guard các giá trị date boolean/`0`/`1` để tránh trường hợp request/binding lỗi tạo SQL `between 0 and 1`.
- CSS cột `TN cần`: rộng hơn, textarea mặc định cao hơn; hover/focus/pin phóng to 380x165, counter theo kích thước mới.

## Files touched
- `app/Services/Operations/SaleOperationService.php`
- `app/Services/Reports/ReportDateRange.php`
- `resources/js/components/layout/AppHeader.jsx`
- `resources/js/pages/Admin/Users/Index.jsx`
- `resources/css/pushsale.css`
- `resources/css/pushsale-layout.css`
- `resources/css/pushsale-sale-workspace.css`
- `public/build/assets/AppLayout-C6T4YjHa.js`
- `public/build/assets/Index-Cp25Z0U-.js`
- `public/build/assets/pushsale-VZglJWi2.css`

## Notes
- Horizon không đổi.
- AutoLogin vẫn đã bị gỡ từ V37, bản này không bật lại.
