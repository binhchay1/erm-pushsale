# V41 — Warehouse Operations legacy parity

## Goal
Rebuild the Thủ kho tác nghiệp screen by decomposing the Pushsale legacy HTML into project components instead of copying the raw HTML:

- shared header/search/filter shell
- grouped warehouse status chips
- legacy warehouse table layout
- row-level action icons inside their original semantic cells
- bottom-left floating hamburger action menu for selected rows

## Source alignment
The uploaded Pushsale source screen contains:

- `m-header-wrap` search/header and hide-zero checkbox
- three filter rows including date type, print status, care status, product, reconciliation, shipping provider, warehouse, deposit, sale/marketing filters, tracking and quantity filters
- `dm-tac-nghiep` grouped status tabs
- `table table-bordered table-multi-select table-sale` warehouse grid
- row-level action icons for care/order/shipping/customer cells
- `nav.action-container` floating action menu with `hidden-actions` and `main-action`

## Files changed

- `resources/js/pages/Admin/Warehouse/Operations.jsx`
  - uses a source-style warehouse page layout instead of card/KPI layout
  - renders status chips before the table

- `resources/js/components/operations/WarehouseFilterPanel.jsx`
  - rebuilt as the warehouse-specific header/search/filter shell
  - keeps all original filter categories visible in the same order

- `resources/js/components/operations/WarehouseOrderTable.jsx`
  - rebuilt table columns and row cells to match Pushsale warehouse operations
  - restored inline icons inside their original cells
  - added selected-row floating action hamburger menu

- `app/Services/Operations/WarehouseOperationService.php`
  - exposes source-style status tabs with `code` and `level`
  - adds row fields needed by the legacy table

- `app/Services/FilterOptionsService.php`
  - exposes sale/marketing leader options and full warehouse filter field set

- `resources/css/pushsale.css`
  - added scoped `V41 Warehouse operations legacy parity` block
  - all rules are scoped under `.ps-wh-legacy-page` or `.ps-wh-floating-actions` to avoid leaking across unrelated pages

## Build

`npm run build` passed and generated:

- `public/build/assets/pushsale-DalFkY5B.css`
- `public/build/assets/Operations-CI3_c0_T.js`
