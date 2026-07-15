# Release validation — V16

## Đã chạy

- Vite production build: thành công, 3.432 module.
- PHP syntax lint: 661 file trong `app`, `config`, `database`, `routes`, `tests`; 0 lỗi cú pháp.
- Test hồi quy V16 đã được thêm cho:
  - provider trùng nhau giữa nhiều tenant;
  - auto waybill theo provider mặc định;
  - webhook hoàn idempotent;
  - nhập hoàn không cộng tồn hai lần;
  - phí giao/hoàn/COD/phụ phí cập nhật vào order.

- Audit template Pushsale: 65 trang, 9 module cũ được gộp, 79 template đã sanitize, 0 lỗi.

## Chưa chạy trong môi trường đóng gói

- PHPUnit chưa chạy vì source release không có `vendor/` và máy đóng gói không có Composer.
- `npm audit` không trả kết quả vì registry audit nội bộ trả HTTP 502. `npm ci` và build vẫn thành công.

## Lệnh kiểm tra sau khi cài dependency

```bash
composer install
php artisan test --filter=WarehouseShippingFlowV16Test
php artisan test --filter=ShipmentReconciliationTest
php artisan test
npm ci
npm run build
```
