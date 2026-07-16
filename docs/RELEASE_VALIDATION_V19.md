# Release validation V19

## Đã kiểm tra

```bash
find app routes config tests -name '*.php' -print0 | xargs -0 -P4 -n1 php -l
# 608 PHP files, status 0

find database/migrations -name '*.php' -print0 | xargs -0 -P4 -n1 php -l
# 76 migration files, status 0
```

## Chưa chạy được trong sandbox

- PHPUnit runtime: source đóng gói không có `vendor/autoload.php`.
- Vite build: V19 không thay đổi frontend; `node_modules` không được đóng gói trong release.

## Test hồi quy thêm mới

- `LandingConnectionDuplicateScopeTest::test_same_phone_on_two_landing_connections_creates_two_real_orders_not_duplicate_leads`
- `LandingConnectionDuplicateScopeTest::test_same_phone_re_submit_on_same_landing_connection_is_duplicate_and_does_not_create_second_order`

