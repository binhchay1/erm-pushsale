# V93 — System business/revenue reports + scrollable menu

## Scope
- Rebuild `/admin/reports/extra/kho-2` as the Pushsale-style **Báo cáo kinh doanh hệ thống** page.
- Keep report pages on the shared `PushsalePageShell` contract instead of page-specific header markup.
- Polish report toolbars/filters for system business, warehouse sales summary, and warehouse sales V2.
- Make long sidebar level-3 flyouts scroll within the dropdown/menu surface.
- Add upsale metrics to system/warehouse revenue reports without double-counting lead/contact counts.

## Pushsale source analysis
- `Báo cáo kinh doanh hệ thống` uses `m-header-wrap > m-header` with title in the first column and search filters in the same header block.
- The main table is `tableReport` with a two-row grouped header: STT, warehouse/name, active warehouse count, Tổng, Khách hàng mới, Khách hàng cũ, and average columns.
- `Báo cáo doanh số V2` uses the same report shell style and a wide two-row grouped table with horizontally scrollable revenue groups.

## Files changed
- `app/Services/Reports/ExtraReportService.php`
  - Expanded filter registry for `kho-2`, `warehouse-sales-summary`, and `warehouse-sales-v2`.
  - Reimplemented `systemBusiness()` on real order/item data.
  - Added upsale item recognition through `item_type=upsell` or origin containing `upsell/upsale`.
  - Added discount-mode-aware `reportRevenue()` and wired it into system/warehouse revenue metrics.
- `resources/js/pages/Reports/ExtraReport.jsx`
  - Added `PushsaleReportToolbar` as common report toolbar contract.
  - Added `SystemBusinessReport` matching Pushsale structure, with upsale columns appended.
  - Reused common toolbar on warehouse sales summary/V2 reports.
- `resources/css/pushsale-v93-system-reports-menu-scroll.css`
  - Report toolbar/table styling.
  - System business table styling.
  - Scrollable sidebar and level-3 flyout menu.
- `resources/js/components/layout/AppSidebar.jsx`
  - Level-3 menu computes max height and top position; scroll does not auto-close flyout.
- `resources/js/lib/pushsaleStyleRegistry.js`
  - Registers V93 CSS.
- `routes/web.php`
  - `/admin/reports/business` redirects to `kho-2` report.
- `tests/Feature/Reports/TemplateSixReportsTest.php`
  - Adds assertions for system business report and upsale metrics.

## Important behavior
- Contact/phone counts still count lead/root contacts, not every supplemental/upsale packet.
- Revenue includes closed orders. Upsale revenue is shown separately but remains included in total order revenue.
- `discount_mode=before_discount` uses gross subtotal minus shipping cost; `after_discount` uses existing `Order::netRevenue()`.
- Long menu dropdowns are scrollable; flyout closes on outside click, resize, navigation, or collapse, but not during scroll.

## Verification run in sandbox
```bash
php -l app/Services/Reports/ExtraReportService.php
php -l tests/Feature/Reports/TemplateSixReportsTest.php
php -l routes/web.php
node --check resources/js/lib/pushsaleStyleRegistry.js
node ./scripts/audit-pushsale-contract.mjs
```

Result: `33 pass, 15 warn, 0 fail`.

`php artisan test` and `pnpm build` were not run in sandbox because `vendor/` and `node_modules/` are not installed here.
