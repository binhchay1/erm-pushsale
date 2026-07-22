# V84 - Full project audit, frontend/backend contract, and stability update

## Scope

This update is a stabilization pass before moving to a new conversation window. It does not try to redesign every screen again. It records the current architecture, locks the CSS/runtime contracts that kept regressing, and adds an automated audit command so future patches can be checked before deploy.

## Current project shape

### Runtime stack

- Laravel backend with Inertia React pages.
- Pushsale/AdminLTE 2 visual shell restored through local vendor assets under `public/vendor`.
- PNPM-only frontend build pinned to `pnpm@9.15.9`.
- Redis/Horizon/Reverb remain the intended production queue/realtime stack.

### Business data flow

1. Admin/company config creates employees, teams, products, combo packages, warehouses, workflow categories, and permission settings.
2. Marketing creates landing connections and budgets.
3. Landing/webhook ingestion records the packet, resolves products/packages, holds/merges landing + upsell packets, and allocates a sale owner.
4. Sale workspace updates operation stage/result/note and closes orders.
5. Warehouse workspace handles export, delivery status, return intake, shipping partner state, and stock movement.
6. Accounting workspace reconciles revenue, COD, fees, deposits, VAT/discount, internal reconciliation, and shipping costs.
7. Customer profile reads the unified customer/order/telesale/warehouse/accounting state with dialogs for operation history, internal messages, purchase history, and supplemental packets.
8. Reporting reads live data for the current period and historical snapshots for past periods.

## Frontend audit

### What is now correct enough to continue

- `AppLayout.jsx` owns the application chrome: topbar, sidebar, content wrapper, mobile backdrop, and style bootstrap.
- `AppSidebar.jsx` uses the Pushsale legacy menu contract: `.left-side`, `.sidebar-menu.ul1`, `.li1/.a1`, `.ul2`, `.ul3`.
- FontAwesome/AdminLTE local vendor assets are present and loaded before runtime page CSS.
- Shared components now exist for:
  - page shell: `resources/js/components/layout/PushsalePageShell.jsx`
  - date range: `resources/js/components/filters/DateRangeFilter.jsx`
  - product select with in-dropdown search: `resources/js/components/filters/ProductSearchSelect.jsx`
  - pagination: `resources/js/components/pagination/PushsalePagination.jsx`
- Landing connections were renamed from numeric page naming into a business file/controller pair:
  - `LandingConnectionsController.php`
  - `Marketing/LandingConnectionsPage.jsx`

### Main remaining technical debt

- There are still 64 legacy React page files named `Page_*` and 64 legacy PHP controllers named `Page*Controller`. These should not all be renamed blindly in one patch. Migrate them by business cluster and keep route redirects.
- CSS is still historically layered from V12 through V83. The risk is not only file count, but broad selectors inside page-specific CSS. V84 centralizes the CSS loader and adds an audit command to expose these risks.
- Several template-backed pages still render via `PushsaleBusinessPage` and injected HTML. They are usable, but their header/body structure is only normalized by CSS and DOM adapters. Long-term target is React page components using `PushsalePageShell` directly.

## Backend audit

### Good structure already present

- Lead ingestion/landing/upsell flow is separated into services under `app/Services/Leads` and `app/Services/Marketing`.
- Sale/warehouse/accounting operations are separated under `app/Services/Operations`.
- Shipping integration is separated by carrier adapters under `app/Services/Shipping`.
- Historical reporting has dedicated services under `app/Services/Reporting`.
- Pushsale legacy page data reads are centralized through:
  - `PushsalePageService`
  - `PushsaleLiveDataService`
  - `PageResourceManager`
- Tenant scope is visible in models/services and platform admin exceptions are explicit for pages such as login history.

### Business wiring status

- Landing connection dialog saves into real models, not template samples: `LandingConnection`, `LandingConnectionSource`, `LandingConnectionProduct`, `LandingConnectionSale`, `MarketingSource`.
- Combo catalog is connected to product catalog through `Product` and `ProductComboItem`.
- Operation categories/workflows are tied to `SaleOperationConfigurationService` and sale status progression.
- Sale/warehouse/accounting workspaces read from order data and shared `OrderOperationPresenter` patterns.
- Customer profile bulk actions use backend controllers, not fake UI-only actions.

### Remaining backend risks

- Some numeric Pushsale page controllers still use the generic `PageResourceManager`. This is acceptable for simple CRUD/config screens, but important business pages should keep being extracted into explicit controllers/services.
- Import/export screens need more end-to-end browser tests because their UI still comes partly from injected legacy templates.
- Some demo/staging commands still exist. They are fine for staging but must stay behind environment/test-mode controls.

## V84 code changes

### 1. Central CSS registry

Added:

```text
resources/js/lib/pushsaleStyleRegistry.js
```

Updated:

```text
resources/js/lib/uiShellStyles.js
```

`uiShellStyles.js` no longer contains a giant manual list of `hasV70`, `hasV71`, etc. Every Pushsale runtime CSS file is registered once in `pushsaleStyleRegistry.js` with a layer name.

### 2. Final stability CSS contract

Added:

```text
resources/css/pushsale-v84-stability-contract.css
```

This locks the highest-risk boundaries:

- page header/body separation
- legacy template host layout
- pagination layout
- sidebar level-3 hover/open state
- floating action buttons round shape

The selectors are deliberately scoped to Pushsale roots and do not use generic `button`, `.btn`, `table`, `input`, or `select` selectors.

### 3. Automated audit command

Added:

```text
scripts/audit-pushsale-contract.mjs
```

Updated package scripts:

```json
{
  "audit:pushsale": "node ./scripts/audit-pushsale-contract.mjs",
  "check:frontend": "node --check resources/js/lib/uiShellStyles.js && node --check resources/js/lib/pushsaleStyleRegistry.js && node ./scripts/audit-pushsale-contract.mjs"
}
```

## Audit result from this patch

```text
Summary: 33 pass, 12 warn, 0 fail.
```

Warnings are expected technical debt, not new breakage:

- old unused version CSS files `pushsale-v12-fixes.css` and `pushsale-v13-fixes.css`
- broad `table` selectors in older page-specific CSS
- 64 legacy numeric React page files
- 64 legacy numeric controller files

## Recommended next migration order

1. Do not keep adding new `pushsale-vNN-*` CSS files for every tiny fix. Use the registry and migrate common pieces into stable shared files by domain.
2. Migrate page names by menu cluster:
   - `1.2 Nhân sự`: users, teams, work shifts, lead distribution rules.
   - `1.3 Sản phẩm`: products, combos.
   - `2 Marketing`: dashboard, ranking, customer profile, landing connections.
   - `4/5/6 Tác nghiệp`: sale, warehouse, accounting.
3. Convert template pages to direct React page components only when the business endpoint is understood.
4. Every future UI change should pass:

```bash
pnpm run check:frontend
php -l <changed php files>
pnpm run build
```

## Handoff note for next conversation

The current target is no longer “make one screenshot look right”. The project needs to preserve this contract:

```text
AppLayout
├── AppHeader
├── AppSidebar
└── content-wrapper
    └── content-inner
        └── ps-page-viewport
            └── PushsalePageShell or template-backed PushsaleBusinessPage
                ├── page header: title + filters/actions
                └── page body: table/form/dialog content
```

Do not fix content spacing by changing sidebar/menu CSS. Do not fix menu hover by changing page content CSS. Shared UI primitives must live in shared components and be scoped by root classes.
