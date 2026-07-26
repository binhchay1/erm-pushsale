# ERM Pushsale V13 — Context handoff

## Thay đổi chính

- Menu 2.4.1 là đầu vào duy nhất để tạo **Kết nối Landing**; route campaign cũ chỉ redirect hoặc trả 410 cho write.
- Một kết nối chứa Landing chính, nhiều upsale, trang cảm ơn, sản phẩm/gói và danh sách Sale.
- Public endpoint nhận form trực tiếp; không cần SDK/JS nhúng của ERM.
- Giá và item do backend dựng lại, không tin dữ liệu tiền từ client.
- `ps_flow` nối xuyên Landing chính → upsale; fallback cùng SĐT trong 90 giây.
- Order được tạo/chia Sale ngay; upsale cộng vào cùng order và tính lại total.
- UI 2.4.1 dùng backend thật, CRUD/batch delete/filter/pagination.
- CSS V13 reset khoảng trắng đầu trang và chuẩn hóa toàn bộ hệ modal về viewport.
- Nguồn bị gỡ dùng soft-delete để giữ attribution lịch sử; endpoint không nhận token của source đã gỡ.
- Chặn dùng `ps_flow` của kết nối khác; mapping gói so khớp chính xác và hỗ trợ `fields` dạng danh sách.

## Deploy

```bash
cd /var/www/erm-pushsale
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
npm ci --ignore-scripts
npm run build
php artisan horizon:terminate
```

Sau deploy, mở menu 2.4.1, tạo một kết nối test, duyệt, submit main + upsale và kiểm tra:

- một customer/order;
- đúng hai packet audit;
- không đổi Sale;
- total bằng tổng item backend;
- order giữ source chính, packet upsale giữ source upsale.
- token từ kết nối khác trả 409; source đã gỡ trả 404 nhưng lịch sử vẫn resolve được tên nguồn.

Chi tiết: `docs/LANDING_CONNECTION_FLOW_V13.md`.
