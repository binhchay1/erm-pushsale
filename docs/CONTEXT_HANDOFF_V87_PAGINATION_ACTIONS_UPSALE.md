# V87 — Pagination, action bubbles, product upsale marker

## User-reported issues

- Marketing dashboard and customer profile pagination looked different.
- Page body/table content started too flush against the header/filter area.
- Customer profile product column did not visibly mark which product line came from upsale.
- Floating action buttons were still rendered as square blocks on some pages.

## Changes

### Shared pagination

- `resources/js/components/reports/ReportPagination.jsx` now renders the same DOM contract as `PushsalePagination`:
  - `.pushsale-pagination.ps-pagination-v81`
  - `.ps-pagination-info`
  - `.ps-pagination-pages`
  - `.ps-pagination-size`
- `resources/css/pushsale-v87-pagination-actions-upsale.css` enforces one final pager style for report pages, marketing dashboard, customer profile and captured-template pages.

### Body spacing

- Added small top breathing room after shared page headers/filter panels:
  - `.ps-page-shell__body { padding-top: 10px }`
  - customer profile table and legacy/captured-template boxes get a small top margin.

### Upsale product lines

- `CustomerProfileService` eager-loads `order_items.origin`.
- `OrderOperationPresenter` exposes per-product:
  - `origin`
  - `isUpsell`
- `Sales/CustomerProfile.jsx` marks upsale lines inside the `Sản phẩm - Số lượng - Đơn giá` column:
  - dashed divider before the first upsale line
  - blue round icon with `title="Upsale"`
  - full supplemental orders also mark product lines as upsale fallback.

### Floating action buttons

- `Sales/CustomerProfile.jsx` adds `ps-v87-round-action` to action buttons.
- V87 CSS applies a stronger round-button contract and `clip-path: circle(...)` to prevent legacy square `!important` rules from leaking through.

## Files changed

- `resources/css/pushsale-v87-pagination-actions-upsale.css`
- `resources/js/lib/pushsaleStyleRegistry.js`
- `resources/js/components/reports/ReportPagination.jsx`
- `resources/js/pages/Sales/CustomerProfile.jsx`
- `app/Services/Customers/CustomerProfileService.php`
- `app/Services/Operations/OrderOperationPresenter.php`
