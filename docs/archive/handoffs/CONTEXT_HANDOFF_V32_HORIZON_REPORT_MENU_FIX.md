# V32 - Restore Horizon provider + fix marketing dashboard 500 + menu/header cleanup

## Vì sao có bản này

Bản V31 đã guard `HorizonServiceProvider` theo hướng không hard-fail khi server thiếu vendor Horizon. Sau khi kiểm tra lại yêu cầu vận hành, project này vẫn dùng Laravel Horizon làm queue master, nên V32 giữ nguyên provider chuẩn của Horizon và xem lỗi thiếu `Laravel\Horizon\HorizonApplicationServiceProvider` là lỗi deploy/vendor trên server, không phải hướng sửa application code.

## Horizon

- Giữ `app/Providers/HorizonServiceProvider.php` kế thừa `Laravel\Horizon\HorizonApplicationServiceProvider`.
- Không đổi sang `Illuminate\Support\ServiceProvider`.
- Sau deploy phải chạy `composer install --no-dev --optimize-autoloader` để đảm bảo package `laravel/horizon` có trong `vendor`.
- Sau deploy nên chạy `php artisan horizon:terminate` để Horizon master nhận code/config mới.

Lệnh kiểm tra nhanh trên server:

```bash
php artisan about | grep -i horizon || true
php artisan horizon:status
php artisan horizon:supervisors
php -r "require 'vendor/autoload.php'; var_dump(class_exists('Laravel\\Horizon\\HorizonApplicationServiceProvider'));"
```

Nếu dòng `class_exists` trả `false`, lỗi nằm ở vendor/autoload hoặc composer install chưa đúng, không phải ở service provider của project.

## Fix HTTP 500 marketing dashboard

Log staging mới chỉ rõ route marketing dashboard chết vì eager-load relation `leadPackets` truyền `Illuminate\Database\Eloquent\Relations\HasMany`, trong khi closure và helper đang ép type `Illuminate\Database\Eloquent\Builder`.

Files changed:

- `app/Services/Reports/PushsaleMarketingDashboardService.php`
  - Closure eager-load `leadPackets` nhận `Builder|Relation` thay vì chỉ `Builder`.
- `app/Support/LeadContactMetrics.php`
  - `applyCountableScope()` nhận `Builder|Relation` để dùng chung được cho query builder và relation eager-load.

## Fix menu/header

Files changed:

- `resources/js/components/layout/AppHeader.jsx`
- `resources/css/pushsale-layout.css`
- `resources/css/pushsale.css`
- Built assets hiện tại trong `public/build/assets/*`

UI behavior:

- Header brand đổi từ `TTGROUP2.ADMIN` sang tên user đang đăng nhập.
- Bỏ chữ `Điểm bảo mật: 1/18`.
- Sidebar desktop không còn shadow.
- Sidebar mở thì content bắt đầu sau menu 252px; sidebar ẩn thì content full width.
- Header brand vẫn giữ nguyên khi sidebar ẩn, không mất chữ tên user.

## QA route scan

`/__erm-test/pages` mặc định quét:

- configured staging URLs,
- URL trong `config/pushsale_navigation.php`,
- static web GET routes không có parameter.

Response có thêm `failed_results` để nhìn nhanh route 500/404/403.

## Deploy đề xuất

```bash
cd /var/www/erm-pushsale
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan horizon:terminate
```

Sau đó gọi endpoint QA pages với secret staging hiện tại và xem `failed_results`.
