# Docs — ERM Pushsale / SaleOps

Agent mới: đọc [`../AGENTS.md`](../AGENTS.md) trước, rồi các file living dưới đây.

## Living docs (chỉ những file này)

| File | Mục đích |
| --- | --- |
| [PROJECT_CONTRACT.md](./PROJECT_CONTRACT.md) | UI/CSS/shell/route/menu — nguồn sự thật kỹ thuật |
| [ARCHITECTURE.md](./ARCHITECTURE.md) | Stack, layer, role, API overview |
| [OPERATIONS.md](./OPERATIONS.md) | Luồng nghiệp vụ sale / kho / kế toán / landing |
| [INTEGRATIONS.md](./INTEGRATIONS.md) | Landing webhook, Pancake, queue/Horizon |
| [DEPLOY.md](./DEPLOY.md) | Deploy salesloop.vn, quyền build, lệnh nhanh |
| [CHANGELOG.md](./CHANGELOG.md) | Milestone gần đây |

**Không** tạo `CONTEXT_HANDOFF_V*`, `RELEASE_VALIDATION_V*`, HTML mẫu trong `docs/`. Không commit template Pushsale.

## Khởi động nhanh

```bash
composer install && pnpm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
composer run dev
```

Demo password: `password`. Email theo `{role}@saleops.local` / `{role}01@…` — xem `database/seeders/AccountSeeder.php`.

```bash
php artisan migrate --force && php artisan db:seed --force
```
