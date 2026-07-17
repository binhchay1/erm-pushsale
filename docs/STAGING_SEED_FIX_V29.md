# V29 — Staging Seeder / Reporting Dirty-Date Hotfix

## Lỗi production/staging đã sửa

Khi chạy `deploy/staging-enable-test-mode.sh`, `InventorySeeder` tạo `WarehouseInventoryMovement`. Model event gọi `ReportDateDirtyTracker`, nhưng code dùng `DB::table(...)->whereKey($dirty->id)`. Ở Laravel runtime hiện tại, query builder sinh ra `WHERE key = ?` thay vì `WHERE id = ?`, gây lỗi:

```text
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'key' in 'WHERE'
```

V29 đổi sang điều kiện rõ ràng:

```php
->where('id', $dirty->getKey())
```

## Seed staging an toàn hơn

Script `deploy/staging-enable-test-mode.sh` tắt reporting facts/archive trong lúc seed demo, rồi bật lại sau seed. Việc seed demo tạo rất nhiều event kho/đơn nên không cần dirty-date tracking chạy ngay trong lúc bootstrap.

## Seed mode

Mặc định vẫn seed full:

```bash
bash deploy/staging-enable-test-mode.sh
```

Chỉ tạo tài khoản đăng nhập tối thiểu:

```bash
STAGING_SEED_MODE=accounts bash deploy/staging-enable-test-mode.sh
```

Không seed:

```bash
STAGING_SEED_MODE=none bash deploy/staging-enable-test-mode.sh
```

## Seeder tối thiểu

Thêm `Database\\Seeders\\StagingAuthSeeder`, idempotent theo email/team/company. Dùng khi cần auto-login admin nhanh mà chưa cần sản phẩm/kho/đơn demo.
