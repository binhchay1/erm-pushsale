# V89 — Page pagination, menu, warehouse, import and distribution polish

## Scope

This pass continues from V88 and fixes the regressions reported on staging:

- `/admin/warehouses` crashed with `empty is not defined`.
- Product/warehouse/admin pages were still using several different pagination contracts.
- Product management content touched the left/right viewport edges.
- Sidebar flyout did not close when clicking outside the menu and had a faint inherited border/hairline.
- Import contact page kept the captured legacy nested `content-wrapper` gap and did not feel aligned with the shared header/body structure.
- Data distribution sale panel was too narrow and compressed account/team information.
- Login history could show the generic migrate/cache warning even when the table data was available, because optional filter option loading was caught in the same exception block as the primary rows.

## Files changed

- `resources/js/pages/Admin/Warehouse/Index.jsx`
  - Defines `emptyWarehouse` form state to fix `empty is not defined`.
  - Uses shared `PushsalePagination`.

- `resources/js/pages/Admin/Products/Index.jsx`
  - Replaces local pagination with `PushsalePagination`.

- `resources/js/pages/Admin/Warehouse/Inventory.jsx`
  - Replaces local pagination with `PushsalePagination`.

- `resources/js/pages/Admin/Warehouse/Operations.jsx`
  - Replaces the local warehouse pager wrapper with `PushsalePagination`.

- `resources/js/pages/Admin/Users/Index.jsx`
  - Replaces legacy `.ps-pagination-bar` with `PushsalePagination`.

- `resources/js/pages/Admin/Teams/Index.jsx`
  - Replaces legacy `.ps-pagination-bar` with `PushsalePagination`.

- `resources/js/pages/Pushsale/BusinessPage.jsx`
  - Routes captured-template pagination through `PushsalePagination`.
  - Adds page-code class/data attributes to the captured template host for safe page-specific CSS.
  - Hides live summary on import-contact pages because it creates unnecessary top gap.

- `resources/js/pages/Pushsale/Pages/Page_1_7_1.jsx`
  - Keeps the login-history retention note but uses shared `PushsalePagination`.

- `resources/js/pages/Legacy/Index.jsx`
  - Routes legacy fallback pagination through `PushsalePagination`.

- `resources/js/layouts/AppLayout.jsx`
  - Clicking outside sidebar/flyout/topbar now closes the menu on desktop and mobile.
  - Escape also closes the sidebar.

- `app/Http/Controllers/Admin/Pushsale/Pages/BasePushsalePageController.php`
  - Separates mandatory row loading from optional filter-option loading.
  - Prevents the generic migrate/cache banner when table data loaded successfully but filter options had an optional failure.

- `resources/css/pushsale-v89-page-pagination-menu-fixes.css`
  - Admin page gutters.
  - Shared pagination final layout.
  - Third-level menu border/hairline cleanup.
  - Import contact compact nested shell cleanup.
  - Data distribution sale-panel width/readability.

- `resources/js/lib/pushsaleStyleRegistry.js`
  - Registers V89 final CSS.

## Validation run in sandbox

```bash
php -l app/Http/Controllers/Admin/Pushsale/Pages/BasePushsalePageController.php
php -l app/Http/Controllers/Admin/Warehouse/WarehouseController.php
node ./scripts/audit-pushsale-contract.mjs
```

Result:

```text
33 pass, 13 warn, 0 fail
```

The new warning is the existing CSS scope audit flagging the word `table` in fully scoped `.psdd-sale-table` selectors. It is scoped to the data distribution page and is not a global table override.

## Deploy checklist

```bash
corepack enable
pnpm install --frozen-lockfile
pnpm build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Then verify:

- `/admin/warehouses` renders and add/edit dialog opens.
- `/admin/products` has small left/right gutter and shared pagination.
- `/admin/users`, `/admin/teams`, warehouse inventory/operations and captured template pages use the same pagination style.
- Sidebar closes when clicking content outside the menu.
- Third-level menu flyout no longer shows inherited blue top hairline.
- `/admin/leads/import` has compact header spacing.
- `/admin/leads` data distribution sale panel is wider and readable.
- `/admin/security/login-history` no longer shows the generic migrate/cache warning when rows are available.
