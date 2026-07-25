# ERM Pushsale — Menu 8.1.1 hourly report + menu 8 audit fix

## Included changes

- Rebuild `8.1.1 Biểu đồ thống kê theo khung giờ` using the current app structure instead of the legacy/static template.
- Share the hourly report implementation across `/ld/thong-ke`, `/admin/reports/hourly`, `/marketing/reports/hourly`, and `/sales/reports/hourly`.
- Add `HourlyStatsSeeder` so demo data is created through real business tables: `orders`, `order_items`, `users`, `products`, and `marketing_sources`.
- Fix upsale report scroll wrapper so the horizontal scrollbar stays directly under the table instead of leaving a fake blank block.
- Normalize several wrong menu 8 report links that were pointing to unrelated reports.
- Add `docs/pushsale-report-contract-map.md` to document report naming rules and controller/service/page ownership.
- Add `scripts/audit-pushsale-report-menu.mjs` to catch common menu 8 route regressions.

## Suggested verification after applying

```bash
composer install
pnpm install
php artisan migrate --seed
php artisan db:seed --class=HourlyStatsSeeder
php artisan test
pnpm build
node scripts/audit-pushsale-report-menu.mjs
node scripts/audit-pushsale-routes.mjs
```

Current sandbox only had source files, without `vendor/` and `node_modules/`, so full `php artisan test` and `pnpm build` were not runnable here.
