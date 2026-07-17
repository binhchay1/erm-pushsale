<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Operational update

Bản này bổ sung màn hình giám sát hệ thống, audit báo cáo theo bản ghi gốc, queue lanes tách biệt và security headers/rate limit. Xem chi tiết trong `docs/OPERATIONS_AUDIT_SECURITY.md`.

Các lệnh hữu ích:

```bash
php artisan audit:report-consistency
php artisan audit:report-consistency --role=sales
php artisan audit:report-consistency --role=warehouse
```

## Horizon / Redis queues

Queue production được quản lý bởi Laravel Horizon, tách riêng từng lane cho webhook, Pancake, shipping, chat, broadcast, notification, report và export. Xem hướng dẫn triển khai/cutover không mất job tại [`docs/HORIZON_REDIS_OPERATIONS.md`](docs/HORIZON_REDIS_OPERATIONS.md).

## Landing + upsell 90 giây

Luồng form chính → trang cảm ơn khác domain → gộp upsell vào cùng đơn được mô tả tại [`docs/LANDING_UPSELL_90S_FLOW.md`](docs/LANDING_UPSELL_90S_FLOW.md).

## V13 — Kết nối Landing thay Campaign

Menu `2.4.1 — Kết nối landing` là điểm khởi tạo luồng Marketing mới. Một kết nối quản lý đồng thời Landing chính, các trang upsale/cảm ơn, mapping sản phẩm backend, danh sách Sale và cách chia số. Form gửi trực tiếp vào URL API riêng của từng source; ERM không yêu cầu nhúng SDK JavaScript.

Tài liệu triển khai và nghiệp vụ:

- [`docs/LANDING_CONNECTION_FLOW_V13.md`](docs/LANDING_CONNECTION_FLOW_V13.md)
- [`docs/CONTEXT_HANDOFF_V13.md`](docs/CONTEXT_HANDOFF_V13.md)

Sau khi cập nhật mã nguồn:

```bash
php artisan migrate --force
php artisan optimize:clear
npm ci --ignore-scripts
npm run build
php artisan horizon:terminate
```

## V14 — Điều hành tài chính và template-five

V14 bổ sung ngân sách trực tiếp trên Kết nối Landing và làm lại Admin Dashboard thành bảng điều hành dòng tiền khép kín. Báo cáo tách doanh số đã chốt, doanh thu đã ghi nhận, tiền đã thu, COD chưa thu; đồng thời trừ Marketing, giá vốn snapshot, vận chuyển, lương/thưởng và chi phí vận hành để ra lợi nhuận ròng.

Tài liệu:

- [`docs/FINANCIAL_CONTROL_V14.md`](docs/FINANCIAL_CONTROL_V14.md)
- [`docs/CONTEXT_HANDOFF_V14.md`](docs/CONTEXT_HANDOFF_V14.md)

## V15 — Template-six reports và menu theo vai trò

V15 tích hợp các trang báo cáo `4.5.1` đến `4.5.8` và `6.3.9`, dùng backend thật và bổ sung upsale vào toàn bộ công thức liên quan. Contact chỉ tính packet landing chính; packet upsale không làm tăng mẫu số. Hai báo cáo doanh số kho giữ đủ 12 nhóm Pushsale, đồng bộ với Marketing Dashboard và tách riêng doanh số upsale.

`partial_delivery` được đưa thành trạng thái doanh thu chính thức. Menu báo cáo được tái sử dụng theo role, quyền khu vực và cấp staff/leader; Sales, Marketing, Warehouse và Accounting nhận đúng route/menu nghiệp vụ thay vì nhìn nguyên cây Admin.

- [`docs/TEMPLATE_SIX_REPORTS_V15.md`](docs/TEMPLATE_SIX_REPORTS_V15.md)
- [`docs/CONTEXT_HANDOFF_V15.md`](docs/CONTEXT_HANDOFF_V15.md)

## V16 — Tác nghiệp kho và giao vận khép kín

V16 làm lại màn thủ kho theo template-seven, thay trang cấu hình giao vận cũ và nối trực tiếp luồng kho với vận đơn, webhook, hàng hoàn, COD và phí vận chuyển. Hệ thống hỗ trợ adapter direct, adapter cấu hình chuẩn cho đối tác trung gian/multi-carrier, timeline webhook, xuất kho idempotent và biên bản nhập hoàn theo từng sản phẩm.

- [`docs/WAREHOUSE_SHIPPING_FLOW_V16.md`](docs/WAREHOUSE_SHIPPING_FLOW_V16.md)
- [`docs/CONTEXT_HANDOFF_V16.md`](docs/CONTEXT_HANDOFF_V16.md)
- [`docs/RELEASE_VALIDATION_V16.md`](docs/RELEASE_VALIDATION_V16.md)

## V17 — Hợp nhất CSS, filter động và modal dùng chung

V17 xây lại hợp đồng giao diện chung thay vì tiếp tục vá từng trang: CSS public/login và ERM được tách entry, 79 template được scope, filter Bootstrap được chuyển sang grid 12 cột không còn cột rỗng, action cell bỏ khung lồng, font nội bộ thống nhất Arial và modal dùng chung được clamp theo viewport.

Lịch sử đăng nhập `1.7.1` lấy user/role/company thật, đồng thời ghi audit cho login thành công, thất bại, bị chặn và logout. Product/team filters và bảng xếp hạng Sales cũng dùng dữ liệu backend, không dùng tên hoặc doanh số chụp từ Pushsale.

- [`docs/UI_SYSTEM_V17.md`](docs/UI_SYSTEM_V17.md)
- [`docs/CONTEXT_HANDOFF_V17.md`](docs/CONTEXT_HANDOFF_V17.md)
- [`docs/RELEASE_VALIDATION_V17.md`](docs/RELEASE_VALIDATION_V17.md)

## V18 — Daily facts, snapshot lịch sử và archive theo tháng

V18 tách report thành hot window và historical window. Hôm nay vẫn live; ngày cũ đọc daily facts và snapshot DB thay vì quét raw tables. Webhook/COD/hàng hoàn đến muộn sẽ đánh dấu đúng ngày bị ảnh hưởng, xóa snapshot và rebuild lại ngày đó. Raw rows được copy sang bảng `*_YYYY_MM` với full-row SHA-256; mặc định không xóa nguồn.

- [`docs/HISTORICAL_REPORTING_V18.md`](docs/HISTORICAL_REPORTING_V18.md)
- [`docs/CONTEXT_HANDOFF_V18.md`](docs/CONTEXT_HANDOFF_V18.md)
- [`docs/RELEASE_VALIDATION_V18.md`](docs/RELEASE_VALIDATION_V18.md)

## V27 Temporary Auto Admin Login

Để tắt tạm đăng nhập trên môi trường test, thêm vào `.env`:

```dotenv
ERM_AUTO_ADMIN_LOGIN=true
ERM_AUTO_ADMIN_LOGIN_HOSTS=erm-pushsale.duckdns.org
```

Sau đó chạy `php artisan optimize:clear && php artisan config:cache`. Xem thêm `docs/AUTO_ADMIN_LOGIN_V27.md`.


## V28 staging remote test

Bật test mode trên domain staging:

```bash
cd /var/www/erm-pushsale
APP_DIR=/var/www/erm-pushsale DOMAIN=erm-pushsale.duckdns.org BASE_URL=http://erm-pushsale.duckdns.org \
  bash deploy/staging-enable-test-mode.sh
```

Sau đó dùng các endpoint được bảo vệ bằng secret:

```text
/__erm-test/health?secret=...
/__erm-test/pages?secret=...
/__erm-test/landing-flow?secret=...
/__erm-test/flow?secret=...
```

Tài liệu: `docs/STAGING_REMOTE_TEST_V28.md`.
