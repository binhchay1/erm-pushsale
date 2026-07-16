# SaleOps audit, monitoring, queue & security notes

## 1. Báo cáo phải khớp từng bản ghi

Mục tiêu là mọi dashboard/report đều lấy số từ cùng source-of-truth:

- `orders` cho đơn, doanh thu, chốt đơn, giao hàng, kho, kế toán.
- `lead_ingestions` cho lead/contact thực nhận.
- Scope role đi qua `ReportQueryService` + `ReportScopeResolver`.
- Metric chuẩn đi qua `ReportMetricService`.

Đã thêm command đối soát:

```bash
php artisan audit:report-consistency
php artisan audit:report-consistency --role=sales
php artisan audit:report-consistency --role=marketing
```

Command này so sánh KPI/chart/bucket với query bản ghi gốc. Màn hình Admin → Giám sát hệ thống → **Đối soát báo cáo** cũng hiển thị kết quả này.

Nếu sau này viết báo cáo mới, không query rời rạc trong controller. Nên đưa query vào `ReportQueryService` hoặc service report riêng nhưng phải dùng cùng filter/scope.

## 2. Giám sát hệ thống

Màn hình Admin → Giám sát hệ thống hiện có thêm:

- CPU model, core, load average, CPU usage theo cache sample.
- RAM, swap.
- Disk app/storage/public.
- Host uptime, OS, Laravel/PHP runtime.
- Process status: nginx/apache, php-fpm, mysql/mariadb, redis, supervisor, queue worker, reverb, node/pm2/vite.
- Queue pending/failed theo từng queue.
- Health checks: database, cache, redis, storage, APP_DEBUG production.
- Inbound events và Laravel logs như cũ.

Các thông tin này đọc từ `/proc`, DB và Laravel runtime nên không cần cài thêm node exporter.

## 3. Queue tách biệt theo luồng

Đã chuẩn hóa queue lane trong `config/saleops.php`:

```env
QUEUE_WEBHOOKS=webhooks
QUEUE_SHIPPING_WEBHOOKS=shipping-webhooks
QUEUE_SHIPMENTS=shipments
QUEUE_MESSAGES=messages
QUEUE_INTERNAL_CHAT_BROADCASTS=broadcasts-internal-chat
QUEUE_PANCAKE_CHAT_SYNC=pancake-chat
QUEUE_PANCAKE_CHAT_BROADCASTS=broadcasts-pancake-chat
QUEUE_NOTIFICATIONS=notifications
QUEUE_TRANSLATIONS=translations
QUEUE_REPORTS_LIVE=reports-live
QUEUE_REPORTS_HISTORY=reports-history
QUEUE_REPORTS_ARCHIVE=reports-archive
QUEUE_REPORTS_MAINTENANCE=reports-maintenance
QUEUE_REPORTS=reports-history
QUEUE_EXPORTS=exports
QUEUE_DEFAULT_NAMED=default
QUEUE_AFTER_COMMIT=true
```

Các job đã được đưa vào queue riêng:

- Lead/webhook: `webhooks`
- Shipping webhook: `shipping-webhooks`
- Tạo vận đơn: `shipments`
- Tin nhắn nội bộ / phụ trợ chat: `messages`
- Broadcast chat nội bộ: `broadcasts-internal-chat`
- Webhook/cache chat Pancake: `pancake-chat`
- Broadcast chat Pancake: `broadcasts-pancake-chat`
- Notification: `notifications`
- Translation: `translations`
- Report live/history/archive/maintenance: `reports-live`, `reports-history`, `reports-archive`, `reports-maintenance`
- Export: `exports`

Production queue processes are managed by one Supervisor program running `php artisan horizon`. Per-lane process limits are defined in `config/horizon.php`; use `deploy/supervisor/horizon.conf.example` and follow `HORIZON_REDIS_OPERATIONS.md`.

## 4. Bảo mật đã thêm

- Middleware `AssignRequestId`: gắn `X-Request-Id` cho mọi request/response để tra log.
- Middleware `AddSecurityHeaders`: thêm `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, HSTS production và CSP optional.
- Rate limit riêng:
  - `lead-intake` cho cổng nhận lead/webhook.
  - `api-auth` cho login API token.
  - `extension-intake` cho Pancake extension.
- Giới hạn payload webhook bằng `WEBHOOK_MAX_PAYLOAD_KB`.
- `QUEUE_AFTER_COMMIT=true` để job không chạy trước khi transaction commit.

Production nên bật thêm:

```env
APP_DEBUG=false
SESSION_SECURE_COOKIE=true
SECURITY_CSP_ENABLED=true
SECURITY_CSP_CONNECT_SRC=https://api.your-domain.com,wss://reverb.your-domain.com
```

## 5. Frontend performance & cleanup

Những điểm đã kiểm tra/chuẩn hóa:

- Bảng dùng `ScrollDataTable` có header xanh `#3782dc`, chữ trắng.
- Màn hình giám sát mới dùng card/table nhẹ, không thêm thư viện chart nặng.
- Dữ liệu monitor chỉ lấy khi mở tab liên quan (`overview`, `queues`, `reports`, `logs`) để tránh query nặng không cần thiết.
- Không xóa file nghiệp vụ nếu chỉ nghi ngờ unused vì route/page động của Inertia dễ bị xóa nhầm. Cleanup an toàn nên dựa trên build + route-list + kiểm thử giao diện.

## 6. Checklist trước deploy

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan audit:report-consistency
```

Sau deploy kiểm tra:

```bash
php artisan queue:failed
php artisan audit:report-consistency
curl -I https://your-domain.com/login
```

## Horizon / Redis queue operations

All production queues now use Redis and are managed by Laravel Horizon with one supervisor per business lane. Do not run legacy `queue:work`/`queue:listen` processes for the same queues. Deployment and zero-loss migration steps are documented in [`HORIZON_REDIS_OPERATIONS.md`](HORIZON_REDIS_OPERATIONS.md).
