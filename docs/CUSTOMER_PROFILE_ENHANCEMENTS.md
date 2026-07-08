# Nâng cấp Hồ sơ khách hàng

## Quyền truy cập

Khu vực quyền `customers` dùng chung với màn hình phân quyền linh động:

- `none`: không xem được trang và API liên quan.
- `view`: xem hồ sơ, lịch sử tác nghiệp, lịch sử mua hàng và tin nhắn nội bộ.
- `full`: có thêm quyền gửi tin nhắn nội bộ.

Mặc định:

- Admin: `full`
- Sales: `full`
- Warehouse/Kho: `full`
- Marketing: `view`
- Accounting/Kế toán: `view`
- Allocator/Chia số: `view`

## Chức năng mới

- Tách riêng cột Địa chỉ và Tin nhắn/Ghi chú khách hàng.
- Dialog lịch sử tác nghiệp.
- Dialog trao đổi nội bộ theo khách hàng, gom theo số điện thoại chuẩn hóa.
- Dialog lịch sử mua hàng, nhóm chi tiết theo từng đơn.
- Nhật ký lead có thêm địa chỉ, ghi chú, sản phẩm, sale, tác nghiệp và mã đơn.
- Menu của mọi vai trò được chia group thống nhất.
- Header bảng dùng màu `#3782dc`, chữ trắng.
- Thời gian hiển thị theo múi giờ Việt Nam dạng `dd/MM/yyyy HH:mm:ss`.

## Chạy cập nhật

```bash
php artisan migrate
php artisan optimize:clear
npm install
npm run build
```

Tạo dữ liệu demo tùy chọn:

```bash
php artisan db:seed --class=CustomerInteractionDemoSeeder
```
