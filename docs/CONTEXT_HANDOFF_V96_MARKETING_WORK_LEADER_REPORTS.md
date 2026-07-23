# V96 — Marketing work report + Marketing leader stats

## Scope

This patch rebuilds the two Marketing report screens requested after V95:

- Menu `2.7.5 Báo cáo công việc` (`marketing-3` / `/admin/reports/extra/marketing-3`).
- Menu `2.8.1 Thống kê trưởng nhóm` (`/admin/reports/team-leaders` and legacy `/ld/marketing/thong-ke-truong-nhom`).

The implementation follows the Pushsale source pages: shared `m-header-wrap` toolbar shape, expandable filter section, wide drag-scroll report tables, and role-scoped backend data instead of copied/static HTML.

## Source UI mapping

### 2.7.5 Báo cáo công việc Marketing

The Pushsale source has:

- Title `Báo cáo công việc Marketing` in `m-header-wrap`.
- First toolbar filters: date type, date range, customer type.
- Advanced filters: sale mode, marketing leader/team, sale team, no closing date limit, marketer/sale, search, product, page size.
- Legend buckets: `Dưới 50 %`, `Từ 50 đến 80 %`, `Trên 80 %`.
- Matrix table: rows are marketing users, columns are sales users, with contact and close-rate cells.

### 2.8.1 Thống kê trưởng nhóm

The Pushsale source has:

- Title `Thống kê trưởng nhóm` in `m-header-wrap`.
- Filters: date type, date range, discount mode, delivery status, leader/team/product/reconciliation/page size.
- Status summary cards: `CHỜ GIAO`, `HỦY VẬN ĐƠN`, `ĐANG GIAO`, `ĐÃ GIAO`, `ĐÃ THANH TOÁN`, `ĐÃ HOÀN`.
- Wide table grouped by marketing/user totals with status-aware numeric bar styling.

## Main changed files

- `app/Services/Reports/ExtraReportService.php`
  - Rebuilt `marketing-3` as a real marketer × sale work matrix.
  - Uses only countable lead contacts for contacts; upsell packets are not counted as new contacts.
  - Applies marketing scope, sale scope, team filters, customer type, product/date/search filters.

- `resources/js/pages/Reports/ExtraReport.jsx`
  - Added `MarketingWorkToolbar` and `MarketingWorkMatrixReport`.
  - Uses `PushsalePageShell` and shared report controls.
  - Adds threshold color classes matching Pushsale legend.

- `resources/css/pushsale-v96-marketing-work-leader-contract.css`
  - Scoped styles only for `.ps-marketing-work-report` and `.ps-marketing-leader-page`.
  - Does not introduce broad global table/sidebar overrides.

- `app/Services/Reports/TeamLeaderStatsService.php`
  - Upsale recognition is now consistent with the project contract: `item_type` or `origin` contains `upsell/upsale`.

- `app/Data/ReportFilterData.php`, `app/Models/Order.php`, `app/Support/LeadContactMetrics.php`
  - Added `customer_type` and `search` support for real backend filtering.

- `resources/js/pages/Reports/TeamLeaderStats.jsx`
  - Adds `no_closing_date_limit` checkbox and keeps menu active code `2.8.1`.

- `config/pushsale_navigation.php`
  - Ensures `2.7.5` and `2.8.1` active menu codes are explicit.

## Checks run

```bash
php -l app/Data/ReportFilterData.php
php -l app/Services/FilterOptionsService.php
php -l app/Models/Order.php
php -l app/Support/LeadContactMetrics.php
php -l app/Services/Reports/ExtraReportService.php
php -l app/Services/Reports/TeamLeaderStatsService.php
php -l app/Http/Controllers/Reports/TeamLeaderStatsController.php
php -l config/pushsale_navigation.php
php -l tests/Feature/Reports/MarketingExtraReportsTest.php
node --check resources/js/lib/pushsaleStyleRegistry.js
node ./scripts/audit-pushsale-contract.mjs
```

Audit result: `33 pass, 17 warn, 0 fail`.

## Not run in sandbox

`php artisan test` and `pnpm build` were not run because the sandbox copy has no `vendor/autoload.php` and no `node_modules`.

Run on the actual project/server after applying:

```bash
corepack enable
pnpm install --frozen-lockfile
pnpm build
php artisan test --filter=MarketingExtraReportsTest
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
