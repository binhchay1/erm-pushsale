# V63 – Warehouse Operations Pushsale Parity

Scope: `/admin/warehouse/operations` and `/warehouse/workspace`.

## Frontend
- Rebuilt the warehouse filter layout to match Pushsale's `warehouse-tac-nghiep` header:
  - title / hide-zero checkbox / keyword + search button in one header row,
  - filter rows grouped in 6-column Bootstrap-like rhythm,
  - reset button at the end of the last filter row.
- Kept the React/Inertia structure; no iframe or legacy postback copying.
- Normalized the warehouse table columns to the Pushsale source order:
  Sale, order/date code, warehouse/PTGH/tracking, care operations, delivery status, customer, address, products, totals, deposit, COD, service fee, support fee, reconciliation.
- Restored the floating circular action menu as a real React action menu, matching the Pushsale source button groups.

## Backend
- Added role-scoped bulk endpoints for admin and warehouse role:
  - `POST /admin/warehouse/orders/bulk/export`
  - `POST /admin/warehouse/orders/bulk/invoices`
  - `POST /admin/warehouse/orders/bulk/update-by-code`
  - and `/warehouse/orders/bulk/...` equivalents.
- Bulk exports stream UTF-8 CSV files for standard, shipping, accounting, and delivery-status update use cases.
- Bulk HĐĐT creates `electronic_invoice_jobs` rows.
- Bulk update-by-code writes an audit note to the order internal reconciliation note.

## CSS
- Added final V63 warehouse contract in `resources/css/pushsale.css` to prevent old CSS from stretching filters/table notes/action buttons.
- Warehouse textarea fields are fixed-height in the table so they do not expand row width/height on hover/focus.
