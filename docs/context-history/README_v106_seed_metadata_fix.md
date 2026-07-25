# V106 - Full seed metadata schema fix

## Lỗi đã sửa

Khi chạy `php artisan db:seed`, `FacebookPageMappingSeeder` ghi `integration_connections.metadata`, nhưng DB staging đã chạy migration cleanup cũ nên cột này không còn tồn tại:

```text
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'metadata' in 'field list'
```

## Cách xử lý trong source

- Thêm migration restore:
  - `database/migrations/2026_07_25_001060_restore_integration_connection_metadata_columns.php`
- Migration restore lại:
  - `integration_connections.metadata`
  - `shipping_partner_connections.metadata`
- Seeder `FacebookPageMappingSeeder` được guard bằng `Schema::hasColumn(...)`, nên không chết nếu ai đó chạy seed trước migrate.
- Thêm test schema:
  - `tests/Feature/Pushsale/IntegrationConnectionMetadataSchemaTest.php`

## Lệnh chạy trên server

```bash
cd /var/www/erm-pushsale
php artisan optimize:clear
php artisan migrate --force
php artisan db:seed
```

Hoặc dùng command tổng để lần sau không quên migrate trước seed:

```bash
php artisan erm:test-all --seed --route-smoke --base-url=https://salesloop.vn --json
```
