# v107 - Server error sweep: schema repair, QA route smoke, PHPUnit fallback

Bản này xử lý các lỗi xuất hiện khi chạy thật trên server bằng:

- Repair schema non-destructive trước khi seed/test:
  - `users.is_active`
  - `integration_connections.metadata`
  - `shipping_partner_connections.metadata`
- `FullBusinessDemoSeeder` tự gọi schema repair trước khi reset/seed, nên `php artisan db:seed` không còn chết nếu thiếu các cột contract.
- `erm:test-all` chạy thêm bước `schema:contract-repair` trước health/seed.
- Health không còn fail giả vì block data counts không có key `ok`.
- Route smoke không dùng HTTP public unauthenticated nữa. Nó dispatch nội bộ qua Laravel kernel, tự đăng nhập user theo role cho từng nhóm route để bắt lỗi 500 thật thay vì báo hàng trăm 401.
- PHPUnit fallback: nếu server deploy `composer install --no-dev` không có `php artisan test`/`vendor/bin/phpunit`, step phpunit sẽ SKIP có ghi chú; các smoke/flow/audit vẫn chạy.
- `reports:verify-facts` trong `erm:test-all` chạy `--repair` để tự build closure/facts thiếu sau khi fresh seed.

## Lệnh chạy trên server

```bash
cd /var/www/erm-pushsale
php artisan optimize:clear
php artisan migrate --force
php artisan erm:test-all --fresh --seed --phpunit --audit --route-smoke --landing-flow --flow --base-url=https://salesloop.vn --json
```

Nếu chỉ muốn vá schema + seed:

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan erm:repair-schema-contract
php artisan db:seed
```

Nếu chỉ muốn bắt 500 ở route/view:

```bash
php artisan erm:test-all --seed --route-smoke --base-url=https://salesloop.vn --json
```
