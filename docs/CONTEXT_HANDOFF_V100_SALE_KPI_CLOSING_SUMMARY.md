# V100 — Sale KPI 2 cleanup + Menu 4.5.2 closing summary

## Scope

- Source request: template-ten-3 (`4.5.2.txt`) and screenshots for Sale KPI 2 + Pushsale closing summary.
- Main goal: remove redundant Sale KPI 2 footer block and rebuild menu `4.5.2` (`Bảng tổng hợp chốt đơn`) as a real backend report, not static HTML data.

## Files changed

- `resources/js/pages/Reports/ExtraReport.jsx`
  - Removed the unused Sale KPI 2 progress strip + explanatory notes under the KPI table.
  - Rebuilt `SaleClosingSummaryReport` to match Pushsale table structure:
    - Title/filter/actions chrome.
    - Date range + sale name search + sale dropdown where allowed + per-page selector.
    - Short pager row (`1 - n / total`) above table.
    - Three-row grouped table header with formula row `(1)…(16)`.
    - First column renders account on top and sale name below.
- `app/Services/Reports/ExtraReportService.php`
  - Menu `4.5.2` now uses `date_from`, `date_to`, `search`, `per_page` filters.
  - `saleClosing()` now calculates from real `orders` and `order_items`:
    - Contact nhận mới = orders assigned to sale within selected range.
    - New closed = assigned in range + closed in range.
    - Contact nhận trước đó = assigned before range + closed in range.
    - Gross/discount/net values derive from order revenue methods.
    - Account uses operational profile employee code when present, else email local-part.
- `resources/css/pushsale-v100-sale-kpi-closing-summary-contract.css`
  - Final scoped CSS for Sale KPI footer cleanup and menu 4.5.2 Pushsale/AdminLTE table parity.
- `resources/js/lib/pushsaleStyleRegistry.js`
  - Registered V100 CSS after V99.
- `routes/web.php`, `app/Http/Controllers/Reports/ExtraReportController.php`
  - Added legacy URL `/ld/sale/bang-tong-hop-ban-hang` mapped to report `sale-2`.
- `resources/js/i18n/locales/{vi,en}/reports.js`
  - Added labels for sale name/account search/export.

## Validation notes

- Ran PHP lint for changed PHP files.
- `php artisan test` was not run in sandbox because the zip does not include `vendor/`.
- For staging demo data, use full seed mode or `php artisan migrate:fresh --seed`; `php artisan test` is for the test database and does not populate production/staging UI data.
