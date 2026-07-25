# ERM Pushsale full QA command

Command chuẩn để seed dữ liệu demo đầy đủ và chạy lại các luồng backend sau mỗi đợt sửa:

```bash
php artisan erm:test-all
```

Mặc định command chạy bộ chuẩn:

1. health DB/cache/storage/route
2. migrate
3. seed full business demo
4. aggregate + verify report facts
5. PHPUnit/Laravel test
6. audit business flow/data/report
7. Landing Connection main + upsell flow
8. E2E order/shipping/reconciliation flow

Các chế độ hay dùng:

```bash
# Dùng cho staging, seed lại và chạy full bộ chuẩn
php artisan erm:test-all --seed --phpunit --audit --landing-flow --flow --base-url=https://salesloop.vn

# Quét thêm toàn bộ page để bắt 500/404 runtime
php artisan erm:test-all --seed --phpunit --audit --landing-flow --flow --pages --all-pages --base-url=https://salesloop.vn

# Reset sạch database staging rồi seed lại từ đầu (destructive)
php artisan erm:test-all --fresh --seed --phpunit --audit --landing-flow --flow --base-url=https://salesloop.vn

# Nhanh: chỉ seed + audit dữ liệu critical
php artisan erm:test-all --quick

# Test đúng 1 nhóm PHPUnit khi vừa sửa kho
php artisan erm:test-all --seed --phpunit --filter=WarehouseVoucherBusinessLinkTest
```

Script deploy tiện dùng:

```bash
APP_DIR=/var/www/erm-pushsale \
BASE_URL=https://salesloop.vn \
BUILD=1 \
PAGES=1 \
bash deploy/test-all.sh
```

Khi thêm luồng mới, thêm step mới vào `app/Console/Commands/ErmTestAllCommand.php`, và bổ sung seed nghiệp vụ vào `database/seeders/FullBusinessDemoSeeder.php`.
