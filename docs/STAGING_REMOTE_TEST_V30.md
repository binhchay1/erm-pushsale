# V30 - Staging remote test fixes

Bản này sửa các lỗi phát hiện khi chạy test trên `erm-pushsale.duckdns.org`:

- Health endpoint không còn gọi `Company::withoutTenant()` vì Company không dùng tenant trait.
- Test endpoint dùng scheme/host thực tế từ request, tránh POST `http://...` bị redirect sang `https://...` rồi đổi thành GET gây lỗi 405 ở landing connection submit.
- Thêm endpoint `/__erm-test/logs?secret=...` để đọc tail Laravel log khi có trang 500.
- `/__erm-test/pages?secret=...&all=1` quét thêm toàn bộ route GET tĩnh không có tham số, ngoài danh sách trang chính.
- Flow E2E không gọi `queue:wait-empty` khi `QUEUE_CONNECTION=sync`.
- Webhook giao vận không ghi `null` vào các cột tiền như `cod_collected` nếu dữ liệu cũ đang null.
- Sale workspace có runtime guard: nếu service report lỗi, trang vẫn render UI kèm alert thay vì trả 500.

Sau deploy chạy:

```bash
cd /var/www/erm-pushsale

git pull
composer install --no-dev --optimize-autoloader
npm ci
npm run build

STAGING_SEED_MODE=accounts \
APP_DIR=/var/www/erm-pushsale \
DOMAIN=erm-pushsale.duckdns.org \
BASE_URL=https://erm-pushsale.duckdns.org \
bash deploy/staging-enable-test-mode.sh
```

Nếu cần seed lại full demo:

```bash
STAGING_SEED_MODE=full \
APP_DIR=/var/www/erm-pushsale \
DOMAIN=erm-pushsale.duckdns.org \
BASE_URL=https://erm-pushsale.duckdns.org \
bash deploy/staging-enable-test-mode.sh
```
