# Docs — ERM Pushsale / SaleOps

Agent mới: đọc [`AGENTS.md`](./AGENTS.md) trước, rồi living docs dưới đây.

## Living docs

| File | Mục đích |
| --- | --- |
| [AGENTS.md](./AGENTS.md) | Conventions agent (UI/CSS/route/docs) |
| [PROJECT_CONTRACT.md](./PROJECT_CONTRACT.md) | UI/CSS/shell/route/menu — nguồn sự thật kỹ thuật |
| [ARCHITECTURE.md](./ARCHITECTURE.md) | Stack, layer, role, API overview |
| [OPERATIONS.md](./OPERATIONS.md) | Luồng nghiệp vụ + packet/landing rules |
| [INTEGRATIONS.md](./INTEGRATIONS.md) | Landing webhook, Pancake, queue/Horizon, carriers |
| [REPORTING.md](./REPORTING.md) | Fact tables, hybrid read, lệnh aggregate |
| [DEPLOY.md](./DEPLOY.md) | Deploy salesloop.vn, quyền build, lệnh nhanh |
| [CHANGELOG.md](./CHANGELOG.md) | Milestone gần đây (ngắn) |

**Không** tạo `CONTEXT_HANDOFF_V*`, `RELEASE_VALIDATION_V*`, HTML mẫu trong `docs/`. Không commit template Pushsale.

## Scripts agent giữ lại

| Script | Việc |
| --- | --- |
| `scripts/audit-pushsale-contract.mjs` | `pnpm audit:pushsale` / `check:frontend` |
| `scripts/enforce-pnpm.cjs` | chặn npm/yarn (`preinstall`) |

## Khởi động nhanh

```bash
composer install && pnpm install
# cần file .env local (APP_KEY, DB) — không commit .env
php artisan key:generate
php artisan migrate --seed
composer run dev
```

Demo password: `password`. Email theo `{role}@saleops.local` / `{role}01@…` — xem `database/seeders/AccountSeeder.php`.
