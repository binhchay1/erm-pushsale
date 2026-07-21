# V78 - Customer profile date filters and floating actions

## Scope

- Remove the orphan border band below the Customer Profile table/pagination.
- Introduce a reusable Pushsale date-range component with validation.
- Apply date-range validation to Customer Profile, Sale Workspace, Warehouse Operations, Accounting Operations, shared report chrome and the generic report filter bar.
- Restore floating action buttons to round Pushsale-style bubbles and keep them tied to table checkbox selection.

## UI changes

- `resources/js/components/filters/DateRangeFilter.jsx`
  - Shared date range control.
  - Auto-corrects `date_to` when it is earlier than `date_from`.
  - Shows toast warning: `Ngày đến không thể nhỏ hơn ngày từ.`
  - Supports compact layout and optional display label.
- `resources/css/pushsale-v78-shared-filters-actions.css`
  - Final scoped CSS for date filters, customer profile pagination cleanup and floating action menu.

## Customer Profile

- `resources/js/pages/Sales/CustomerProfile.jsx`
  - Date inputs now use `DateRangeFilter`.
  - Export buttons now require selected rows, matching the table checkbox workflow.
  - Pagination/table band is removed by V78 CSS.

## Operations pages

- `SaleWorkspaceFilters.jsx`, `WarehouseFilterPanel.jsx`, `AccountingOperationFilters.jsx`
  - Reuse `DateRangeFilter` so date validation is consistent across the three operation workspaces.

## Reports

- `PushsaleReportChrome.jsx` and `ReportFilterBar.jsx`
  - Shared date controls are now routed through `DateRangeFilter` so the common report pages do not drift.

## Backend note

Customer Profile floating actions continue using the existing backend endpoints:

- `GET /customers/export`
- `POST /customers/bulk/reallocate-now`
- `POST /customers/bulk/queue-reallocation`
- `POST /customers/bulk/recall`
- `DELETE /customers/bulk/operation-history`

V78 does not introduce fake/demo data.
