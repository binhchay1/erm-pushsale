# Pushsale UI V7 – dữ liệu thật và luồng nghiệp vụ

## Nguyên tắc

- File HTML/PNG trong template chỉ quyết định cấu trúc và giao diện.
- Không sử dụng các dòng dữ liệu được chụp từ Pushsale làm dữ liệu runtime.
- Dữ liệu bảng, bộ lọc, tổng hợp và dialog được đọc từ database của ERM.
- Mỗi mã menu vẫn có route/controller/component riêng; các màn cùng nghiệp vụ có thể dùng chung service truy vấn.
- Trường chưa tồn tại trong mô hình dữ liệu được trả `null`/`—`, không tự tạo số liệu giả.

## Luồng được dùng lại

| Nhóm màn hình | Nguồn dữ liệu/luồng |
|---|---|
| Hồ sơ khách hàng, telesale, upsale | `orders`, `order_items`, `lead_ingestions`, `OrderOperationPresenter`, message/history endpoints hiện có |
| Nhập contact | `ManualLeadController` và luồng import/LeadOrderFactory hiện có |
| Nhân sự, nhóm | `users`, `teams`, role/permission hiện có |
| Sản phẩm, combo | `products`, category/attribute/value và `product_combo_items` |
| Marketing, nguồn dữ liệu | `marketing_sources`, `lead_ingestions`, Facebook mapping và partner connections |
| Kho | `WarehouseInventoryService`, `WarehouseOperationService`, inventory movements/vouchers |
| Báo cáo sale | `orders` + filter ngày/sale/team/product thực tế |
| Chia data | đơn đã gán sale (`sale_user_id`, `assigned_at`) và packet lead thật; không dựng wave giả |
| Care đơn | `care_distribution_rules` và số contact thực tế trong khoảng ngày |
| Kế toán/KPI | expenses, expense categories/groups/units, monthly KPI và order revenue |

## Các thay đổi V7

1. Xóa dữ liệu hàng mẫu trong template; React chỉ portal dữ liệu backend vào vùng tbody.
2. Hồ sơ khách hàng dùng query SQL và phân trang server; không tải danh sách demo.
3. Kho dùng service tồn kho/vận hành hiện có.
4. Bảng chia data dùng `assigned_at`, sale, team, khách mới/cũ/trùng và packet manual thật.
5. Bảng V2 không còn tự đặt `quota = max(10, contacts)` hoặc `wave = 1`. Trường chưa được hệ thống lưu trả trống.
6. Báo cáo tối ưu sale không còn giả thời lượng gọi bằng 0; khi chưa tích hợp tổng đài sẽ hiển thị trống.
7. Dialog CRUD đọc lại record thật từ model và lưu qua validation/resource tương ứng.

## Kiểm tra triển khai

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan route:cache
php artisan config:cache
php artisan view:cache
php artisan horizon:terminate
```

Bản build chứa sẵn `public/build`. Sau deploy nên kiểm tra trực tiếp các route nghiệp vụ chính và log Laravel.
