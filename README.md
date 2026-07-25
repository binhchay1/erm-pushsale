# ERM Pushsale

Laravel/Inertia ERM Pushsale project.

## Project contracts

Read these files before changing UI/business flow:

- `docs/PROJECT_CONTRACT.md`
- `docs/LANDING_CONNECTION_BACKEND_RESET_V122.md`
- `docs/context-history/README_v125_manual_distribution_landing_fix.md`

## Deploy checklist

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan erm:repair-schema-contract
pnpm build
php artisan erm:test-all --route-smoke --smoke-limit=30 --json
```

Do not delete legacy runtime vendor assets unless all references are migrated:

- `public/vendor/font-awesome`
- `public/vendor/adminlte2`
- `public/vendor/bootstrap`
