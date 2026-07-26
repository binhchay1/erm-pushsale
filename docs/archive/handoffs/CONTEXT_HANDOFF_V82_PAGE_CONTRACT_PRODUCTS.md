# V82 - Page contract, product select and landing route rename

## Scope

V82 fixes the regressions reported after V81 without touching the sidebar/content from unrelated selectors.

## What changed

- Added `PushsalePageShell` as the shared page contract: header + body.
- Converted `PageHeader`, `PushsalePageChrome`, and `PushsalePageFrame` into adapters that render through the same page shell.
- Added scoped CSS `pushsale-product-page-contract.css` with three boundaries only:
  1. `.ps-page-shell` for page header/body layout.
  2. `.pushsale-main-sidebar` / `.pushsale-third-menu` for sidebar level-3 menu colors.
  3. `.ps-product-search-select-v82` for product/combo select behavior.
- Rebuilt `ProductSearchSelect` so the control looks like a normal select and the search input is inside the dropdown.
- Renamed landing connection files to business names:
  - `Page2_4_1Controller` -> `LandingConnectionsController`
  - `Page_2_4_1.jsx` -> `Marketing/LandingConnectionsPage.jsx`
- Updated routes and config references for the renamed landing connection page.

## Notes

The full numeric page-file migration should be done gradually per business area because many legacy pages still share generic resource plumbing. V82 removes the numeric name from the page currently being edited and introduces the shared shell so future pages can be migrated safely without CSS bleeding.
