# Release validation V22

## Kiểm tra cần chạy sau deploy

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan horizon:terminate
```

## Smoke test nghiệp vụ

1. Mở `/admin/leads` và kiểm tra màn Phân bổ data.
2. Lọc theo khoảng ngày có data landing pending.
3. Nhập số lượng phân bổ theo sản phẩm.
4. Chọn Sale và bấm Phân bổ data.
5. Kiểm tra đơn mới nằm trong workspace của Sale.
6. Mở `/admin/shipping-partners`, lưu từng hãng vận chuyển.
7. Tạo vận đơn thử từ Kho, kiểm tra webhook vẫn update status/COD/phí.

## Lưu ý

Frontend public/login không dùng CSS nội bộ V22. Giám sát hệ thống vẫn giữ giao diện riêng vì không có template tương ứng của Pushsale.
