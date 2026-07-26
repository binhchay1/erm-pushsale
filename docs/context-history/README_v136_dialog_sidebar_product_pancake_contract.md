# v136 — Dialog/sidebar/product/Pancake cleanup

## Mục tiêu
- Chốt lại lỗi hover menu cấp 2 bằng runtime style + canonical CSS, không phụ thuộc legacy cascade.
- Fit lại cột sản phẩm/số lượng/đơn giá dùng chung cho Sale/Kho/Kế toán/Hồ sơ khách hàng.
- Đồng bộ dialog tin nhắn nội bộ/Pancake theo một shell chung, tránh duplicate close button và UI lệch.
- Giảm spam toast realtime/chia số, chỉ giữ toast mới nhất trong một khoảng ngắn.
- Thêm `php artisan pancake:doctor` để kiểm tra setup Pancake trước khi test live.

## File chính
- `resources/js/components/layout/AppSidebar.jsx`
- `resources/css/pushsale-adminlte-canonical-contract.css`
- `resources/js/components/customers/pushsale/PushsaleCustomerDialogs.jsx`
- `resources/js/hooks/useRealtimeNotifications.js`
- `resources/js/pages/Admin/DataDistribution/Index.jsx`
- `app/Console/Commands/PancakeDoctorCommand.php`

## Test gợi ý
```bash
php artisan optimize:clear
pnpm build
php artisan pancake:doctor --json
php artisan erm:test-all --route-smoke --smoke-limit=30 --json
```
