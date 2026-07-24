# V81 - Landing dialog, product search filters, shared pagination

## Scope

- Polish `2.4.1 Kết nối landing - website` create/update dialog.
- Add reusable searchable product/package picker.
- Wire product/package search into main product filters used by landing, marketing dashboard, customer profile, sale workspace, warehouse workspace, accounting workspace, and report filter bar.
- Add reusable `PushsalePagination` component and apply it to landing connections and marketing dashboard.
- Keep sidebar/menu CSS isolated from this change.

## Business notes

- Landing connection backend already persists real records through `LandingConnectionManager` and validates connection/sources/products/sale priorities.
- V81 keeps the same endpoints and adds stricter catalog metadata:
  - product options include `type` so UI can distinguish product vs combo/package.
  - validation rejects combo catalog rows saved as single product and single product rows saved as combo.
- Product filters now only render product/package records provided by backend filter options; no hardcoded catalog values.

## Frontend contract

- `ProductSearchSelect` is the shared single-select product/package control.
- `ProductMultiAdder` lets the landing dialog add multiple products/packages at once.
- `PushsalePagination` is the shared AdminLTE-style pager to replace ad-hoc page pagers.
- Styles live in `pushsale-dialog-pagination-products.css`, loaded after V80.

## Files changed

- `app/Http/Controllers/Admin/Pushsale/Pages/Page2_4_1Controller.php`
- `resources/js/components/filters/ProductSearchSelect.jsx`
- `resources/js/components/pagination/PushsalePagination.jsx`
- `resources/js/pages/Pushsale/Pages/Page_2_4_1.jsx`
- `resources/js/pages/Admin/Marketing/Dashboard.jsx`
- `resources/js/pages/Sales/CustomerProfile.jsx`
- `resources/js/components/operations/pushsale/SaleWorkspaceFilters.jsx`
- `resources/js/components/operations/WarehouseFilterPanel.jsx`
- `resources/js/components/operations/AccountingOperationFilters.jsx`
- `resources/js/components/reports/ReportFilterBar.jsx`
- `resources/css/pushsale-dialog-pagination-products.css`
- `resources/js/lib/uiShellStyles.js`
