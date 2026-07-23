# V95 — Marketing revenue summary / V2 parity

## Scope

This patch separates Marketing menu `2.7.2 Báo cáo doanh số` and `2.7.3 Báo cáo doanh số V2` into two real report screens instead of reusing the same legacy marketing revenue route.

## Pushsale templates analyzed

- `Pasted text (2)(1).txt`: `/ld/marketing/bao-cao/bao-cao-doanh-so`
  - Header uses `m-header-wrap` with dimension selector defaulting to `1.Kho`.
  - Table is a revenue-summary report. Each selected revenue group is shown as a compact 3-column block.
- `Pasted text(22).txt`: `/ld/marketing/bao-cao-doanh-so-v21`
  - Header uses the same report shell but the table is V2.
  - Table keeps contact/closed/rate columns, then shows colored revenue group blocks.

## Implementation

### Routing / menu

- `config/pushsale_navigation.php`
  - `2.7.2` -> `/admin/reports/extra/marketing-sales-summary`
  - `2.7.3` -> `/admin/reports/extra/marketing-sales-v2`
- `ExtraReportController::activeMenuCode()` maps these keys to the correct Marketing menu items for Admin and Marketing.

### Backend

- `ExtraReportService` adds two report keys:
  - `marketing-sales-summary`
  - `marketing-sales-v2`
- Both reports reuse the warehouse revenue aggregation engine with `scopeMarketing: true`, so the report is still grouped by warehouse like the Pushsale default `1.Kho`, but data is filtered by marketing visibility.
- Filters are real backend filters:
  - date type / date range
  - parent product / product
  - discount mode
  - delivery status
  - reconciliation status
  - warehouse
  - marketing leader / marketing team
  - per page
  - no closing date limit
- Upsale metrics are appended as an explicit group:
  - `upsell_qty`
  - `upsell_revenue`
  - `upsell_share`

### Frontend

- `ExtraReport.jsx`
  - `WarehouseSalesSummaryReport` now renders the 2.7.2 compact revenue summary table.
  - `WarehouseSalesV2Report` now renders the 2.7.3 V2 table with colored cells.
  - `RevenueOverviewToolbar` standardizes the report header/filter shell for these two pages.
- `resources/css/pushsale-v95-marketing-sales-report-contract.css`
  - Scoped CSS for summary and V2 report pages only.
  - Does not override the global sidebar/header table styles.
- `pushsaleStyleRegistry.js`
  - Registers the V95 CSS as a final contract layer.

## Verification run in sandbox

```bash
php -l app/Services/Reports/ExtraReportService.php
php -l app/Http/Controllers/Reports/ExtraReportController.php
php -l app/Services/Reports/ReportSnapshotCache.php
php -l config/pushsale_navigation.php
php -l lang/vi/reports.php
php -l lang/en/reports.php
node --check resources/js/lib/pushsaleStyleRegistry.js
node ./scripts/audit-pushsale-contract.mjs
```

Audit result: `33 pass, 17 warn, 0 fail`.

`node --check` cannot parse `.jsx` directly in this sandbox because Node does not know the `.jsx` extension. Full JSX/Vite validation still needs `pnpm build` on the project environment with `node_modules`.
