# ERM Pushsale

Laravel + Inertia/React ERM. Deploy target: `salesloop.vn`.

## Living docs

Đọc trước khi sửa UI / route / nghiệp vụ:

- [`AGENTS.md`](./AGENTS.md) — conventions agent
- [`docs/README.md`](./docs/README.md) — index
- [`docs/PROJECT_CONTRACT.md`](./docs/PROJECT_CONTRACT.md) — UI/CSS/shell/menu
- [`docs/ARCHITECTURE.md`](./docs/ARCHITECTURE.md) · [`docs/OPERATIONS.md`](./docs/OPERATIONS.md) · [`docs/INTEGRATIONS.md`](./docs/INTEGRATIONS.md) · [`docs/DEPLOY.md`](./docs/DEPLOY.md)

Không tạo `CONTEXT_HANDOFF_V*` / HTML template trong `docs/`.

## Quick start

```bash
composer install && pnpm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
composer run dev
```

Demo password: `password`. Email theo `{role}@saleops.local` — xem `database/seeders/AccountSeeder.php`.

## Deploy checklist

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan erm:repair-schema-contract
pnpm build
php artisan erm:test-all --route-smoke --smoke-limit=30 --json
```

Giữ vendor runtime legacy nếu còn reference:

- `public/vendor/font-awesome`
- `public/vendor/adminlte2`
- `public/vendor/bootstrap`
