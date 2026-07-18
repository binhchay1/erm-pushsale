# V28 — Staging Remote Test Mode

Mục tiêu: deploy lên `http://erm-pushsale.duckdns.org/` rồi có thể test từ xa toàn bộ UI route, health, data seed và luồng business thật mà không cần đăng nhập thủ công.

## 1. Bật test mode

Copy các biến trong `.env.staging-test.example` vào `.env`, tối thiểu:

```env
APP_URL=http://erm-pushsale.duckdns.org
ERM_AUTO_ADMIN_LOGIN=false
ERM_AUTO_ADMIN_LOGIN_HOSTS=erm-pushsale.duckdns.org

ERM_STAGING_TEST_MODE=true
ERM_STAGING_TEST_HOSTS=erm-pushsale.duckdns.org
ERM_STAGING_TEST_BASE_URL=http://erm-pushsale.duckdns.org
ERM_STAGING_TEST_SECRET=<secret-dai-random>
ERM_STAGING_TEST_ALLOW_ARTISAN=true
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
```

Hoặc chạy script:

```bash
cd /var/www/erm-pushsale
APP_DIR=/var/www/erm-pushsale DOMAIN=erm-pushsale.duckdns.org BASE_URL=http://erm-pushsale.duckdns.org \
  bash deploy/staging-enable-test-mode.sh
```

Script sẽ in ra secret và các URL test.

## 2. Endpoint để test từ xa

Tất cả endpoint đều yêu cầu `?secret=...`. Khi `ERM_STAGING_TEST_MODE=false` hoặc sai host/secret, endpoint trả 404.

```text
GET /__erm-test/health
```

Kiểm tra PHP, DB, cache, storage, queue, route count và số lượng data chính.

```text
GET /__erm-test/bootstrap?reset=1&campaigns=2&per_campaign=8
```

Chạy migrate, seed, tạo dữ liệu demo pipeline. Chỉ bật trên staging.

```text
GET /__erm-test/pages
```

Quét các trang GET quan trọng từ chính domain public để bắt 500, thiếu route, thiếu component, lỗi auth redirect, body có signature lỗi PHP.

```text
GET /__erm-test/landing-flow
```

Tạo một kết nối Landing thật có ngân sách, nguồn chính, nguồn upsale, mapping sản phẩm, Sale nhận số. Sau đó gọi HTTP thật vào endpoint public:

```text
/api/v1/landing-connections/{connection}/sources/{source}/submit
```

Luồng kiểm tra:

```text
Landing chính submit
→ nhận flow_token
→ upsale submit với ps_flow
→ cùng order có item chính + item upsale
→ chốt đơn
→ cập nhật giao vận manual/delivered
```

```text
GET /__erm-test/flow
```

Luồng e2e legacy campaign/webhook cũ để kiểm tra backward compatibility.

```text
GET /__erm-test/audit
```

Chạy audit business-flow và verify report facts.

## 3. CLI smoke test trên server

```bash
cd /var/www/erm-pushsale
php artisan staging:smoke --bootstrap --reset --landing-flow --flow --pages --campaigns=2 --per-campaign=8
```

## 4. Quy tắc tắt test mode sau khi xong

```env
ERM_AUTO_ADMIN_LOGIN=false
ERM_STAGING_TEST_MODE=false
ERM_STAGING_TEST_ALLOW_ARTISAN=false
```

Sau đó:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan horizon:terminate
```

Không bật các endpoint này trên production thật.
