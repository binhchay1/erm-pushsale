# Laravel Horizon + Redis queues

## Kiến trúc

Toàn bộ asynchronous workload của ERM SaleOps dùng queue driver `redis` và được Horizon quản lý. Mỗi nhóm nghiệp vụ có một queue và một Horizon supervisor riêng để một luồng chậm hoặc lỗi không giữ worker của luồng khác:

| Queue | Mục đích |
|---|---|
| `webhooks` | Lead/webhook chung |
| `pancake-orders` | Import/chốt đơn Pancake |
| `shipping-webhooks` | Nhận callback vận chuyển |
| `shipments` | Tạo/cập nhật vận đơn |
| `messages` | Xử lý tin nhắn nội bộ |
| `broadcasts-internal-chat` | Realtime chat nội bộ |
| `broadcasts-dashboard` | Realtime dashboard/workspace |
| `broadcasts-notifications` | Realtime notification |
| `pancake-chat` | Đồng bộ webhook/chat Pancake |
| `broadcasts-pancake-chat` | Realtime chat Pancake |
| `notifications` | Tạo và gửi notification |
| `translations` | Dịch nội dung nền |
| `reports` | Tổng hợp báo cáo nền |
| `exports` | Export lớn, timeout dài |
| `default` | Tác vụ không phân loại |

Redis được tách logical database:

- cache: DB `1`;
- queue payloads: DB `2` qua Redis connection `queue`;
- Horizon metadata/metrics: DB `3` qua Redis connection `horizon_meta`.

Không đổi tên source Redis connection thành `horizon`; tên đó được Horizon dùng nội bộ.

## Cài đặt

Yêu cầu PHP CLI có `pcntl`, `posix`, và Redis server/extension `phpredis` hoạt động.

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Các biến chính trong `.env`:

```dotenv
QUEUE_CONNECTION=redis
QUEUE_AFTER_COMMIT=true
REDIS_QUEUE_CONNECTION=queue
REDIS_QUEUE_DB=2
REDIS_HORIZON_DB=3
REDIS_QUEUE_RETRY_AFTER=1200
REDIS_QUEUE_BLOCK_FOR=5
CACHE_STORE=redis
```

`retry_after=1200` phải lớn hơn timeout dài nhất của supervisor. Hiện export có timeout 900 giây; không giảm `retry_after` xuống bằng hoặc thấp hơn giá trị đó.

## Chuyển từ database queue sang Redis không mất job

Không chỉ đổi `.env` rồi tắt worker cũ. Job đã nằm trong bảng `jobs` sẽ không tự chuyển sang Redis.

1. Deploy source và cài dependency Horizon, nhưng chưa dừng worker database.
2. Tạm ngăn phát sinh job mới trong một cửa sổ bảo trì ngắn hoặc deploy đồng bộ toàn bộ web nodes.
3. Drain từng queue database cũ:

```bash
php artisan queue:work database \
  --queue=webhooks,pancake-orders,shipping-webhooks,shipments,messages,broadcasts-internal-chat,broadcasts-dashboard,broadcasts-notifications,pancake-chat,broadcasts-pancake-chat,notifications,translations,reports,exports,default \
  --stop-when-empty \
  --tries=3 \
  --timeout=900
```

4. Xác nhận bảng `jobs` bằng 0. System Monitor cũng hiển thị cảnh báo **database queue pending** nếu còn job cũ.
5. Đặt `QUEUE_CONNECTION=redis`, clear/cache config.
6. Dừng toàn bộ Supervisor program cũ chạy `queue:work` hoặc `queue:listen`.
7. Cài `deploy/supervisor/horizon.conf.example`, chỉnh path/user rồi start đúng **một** Horizon master cho mỗi application server.
8. Mở `/horizon` và tab **Horizon / Queue** trong System Monitor để xác nhận tất cả supervisor running.

## Supervisor

Ví dụ:

```bash
sudo cp deploy/supervisor/horizon.conf.example /etc/supervisor/conf.d/erm-saleops-horizon.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start erm-saleops-horizon
```

Không chạy các process `queue:work` cho những queue trên cùng lúc với Horizon. System Monitor cảnh báo khi phát hiện legacy worker.

Khi deploy code mới:

```bash
php artisan horizon:terminate
```

Supervisor sẽ tự khởi động Horizon với code/config mới. `fast_termination=true` cho phép deploy mới bắt đầu trong khi worker cũ hoàn tất job đang xử lý.

## Scheduler và metrics

Cron scheduler phải chạy mỗi phút:

```cron
* * * * * cd /var/www/erm-saleops && php artisan schedule:run >> /dev/null 2>&1
```

Project chạy `horizon:snapshot` mỗi 5 phút để Horizon có throughput/runtime metrics.

## Tuning

Số process mỗi lane lấy từ `.env`, ví dụ:

```dotenv
HORIZON_WEBHOOK_MAX_PROCESSES=6
HORIZON_PANCAKE_ORDER_MAX_PROCESSES=4
HORIZON_EXPORT_MAX_PROCESSES=2
```

Nguyên tắc:

- webhook/chat/broadcast: nhiều process, timeout ngắn;
- report/export/translation: ít process, memory và timeout cao hơn;
- không tăng đồng loạt process nếu MySQL/Redis/API đối tác đang là bottleneck;
- theo dõi wait time, throughput và failed jobs trước khi chỉnh.

## Bảo mật dashboard

Route `/horizon` dùng middleware `web`, `auth` và gate `viewHorizon`. Chỉ tài khoản có `canManagePlatform()` được xem payload, exception và metadata của job. Không public Horizon trực tiếp qua một subdomain bỏ qua session/auth của ứng dụng.

## Báo cáo Allocator từng bị lệch

Nguyên nhân cũ nằm trong `ReportScopeResolver::scopeAllocatorOrders()`:

```php
$query->whereNotNull('sale_user_id')->orWhereNull('sale_user_id');
```

Điều kiện này vừa là tautology, vừa có `OR` không group nên vượt ra ngoài điều kiện ngày. Với 1.003 đơn và 31 ngày, mỗi bucket đếm lại toàn bộ 1.003 đơn, tạo ra `31.093`.

Scope Allocator giờ trả nguyên builder, giữ nguyên date/product/source filter. Regression test `test_allocator_daily_order_series_respects_the_report_date_range` đảm bảo tổng chuỗi ngày bằng số đơn nguồn trong kỳ.
