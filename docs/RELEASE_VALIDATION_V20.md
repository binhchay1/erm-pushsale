# Release validation V20

## Đã kiểm tra trong package

- PHP syntax lint các file mới/sửa: PASS.
- Thêm migration `2026_07_16_000000_create_customer_phone_locks_table.php`.
- Thêm test hồi quy `LandingConnectionDuplicateScopeTest::test_same_phone_on_two_connections_keeps_two_orders_but_same_sale_owner`.

## Chưa chạy được trong sandbox

Không chạy PHPUnit runtime vì package không có `vendor/autoload.php` và Composer trong môi trường đóng gói. Sau deploy hoặc sau `composer install`, chạy:

```bash
php artisan test --filter=LandingConnectionDuplicateScopeTest
php artisan audit:business-flow
```

## Kỳ vọng nghiệp vụ

- Không mất đơn khi cùng khách vào hai landing connection khác nhau.
- Không có hai Sale active gọi cùng một SĐT.
- Báo cáo nguồn/doanh thu vẫn tách theo từng order và landing connection.
