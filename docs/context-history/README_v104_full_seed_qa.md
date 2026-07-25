# v104 — full seed + ERM QA command

## Vì sao có bản này

Sau khi copy zip v103 đè lên repo, commit log cho thấy deploy build được, nhưng có 2 rủi ro cần khóa lại:

1. Một số asset legacy `/public/vendor/adminlte2` và `/public/vendor/font-awesome` bị xóa khỏi repo trong khi Blade/React shell vẫn còn reference các path đó.
2. Dữ liệu seed nằm rải ở nhiều seeder, nên mỗi lần thêm luồng mới rất dễ quên seed hoặc quên test route/backend tương ứng.

## Thay đổi chính

- Thêm `database/seeders/FullBusinessDemoSeeder.php` làm nguồn seed chuẩn cho toàn bộ business ERM Pushsale.
- `DatabaseSeeder` giờ gọi thẳng `FullBusinessDemoSeeder`, nên `php artisan db:seed --force` cũng sinh đầy đủ demo.
- Mở rộng `DemoResetSeeder` và `FlowDataResetSeeder` để xóa sạch các bảng mới: landing connection, customer interaction, Pancake, voucher kho, return receipt, report facts/snapshots, data distribution, v.v.
- `StagingTestService` dùng `FullBusinessDemoSeeder`, endpoint `__erm-test/demo-ui` và `bootstrap` sẽ có dữ liệu đầy đủ hơn.
- Thêm command chuẩn: `php artisan erm:test-all`.
- Thêm script wrapper: `deploy/test-all.sh`.
- Restore các CSS compatibility path tối thiểu dưới `/public/vendor/...` để tránh 404 asset sau khi v103 xóa thư mục vendor cũ. Không bundle font binary.

## Lệnh chạy nhanh

```bash
php artisan erm:test-all
```

Chạy staging full hơn:

```bash
APP_DIR=/var/www/erm-pushsale \
BASE_URL=https://salesloop.vn \
BUILD=1 \
PAGES=1 \
bash deploy/test-all.sh
```

Reset sạch DB staging trước khi seed lại:

```bash
php artisan erm:test-all --fresh --seed --phpunit --audit --landing-flow --flow --pages --all-pages --base-url=https://salesloop.vn
```

Test riêng luồng kho:

```bash
php artisan erm:test-all --seed --phpunit --filter=WarehouseVoucherBusinessLinkTest
```
