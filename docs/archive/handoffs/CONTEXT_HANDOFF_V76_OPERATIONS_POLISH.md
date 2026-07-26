# V76 - Sale/Warehouse operation workspace polish

## Scope

- Fix third-level sidebar flyout hover residue after leaving menu items.
- Rebuild `TN cần` hover/pin behavior in sale workspace.
- Remove inner vertical table scroll for sale workspace so the page owns vertical scrolling.
- Re-align warehouse operation filter/header layout with the shared Pushsale/AdminLTE shell.
- Normalize warehouse floating action menu so action buttons do not render as scattered colored squares.

## Frontend changes

### Sidebar

- Added final cascade cleanup for `li2/a2` hover/flyout states.
- Removed pseudo-element/border/shadow residue that could remain after level-3 flyout closes.
- Kept level-3 flyout compact, white, square, and positioned from the sidebar edge.

### Sale workspace

- `OperationNoteEditor` now has separate states:
  - hover opens enlarged editor temporarily,
  - mouse leave closes it automatically,
  - click/focus pins it,
  - Escape/compress/save closes it.
- Enlarged note editor uses a fixed portal with a short grow animation.
- Sale table wrapper uses page scroll vertically and table horizontal scroll only.

### Warehouse workspace

- `WarehouseFilterPanel` now renders as a coherent header + filter grid.
- Filter rows use fixed grid contracts instead of collapsing into one full-width field per row.
- Floating warehouse actions are fixed to the bottom-left and hidden until hover/open/focus.
- Disabled action buttons stay inside the action rail and no longer leak into page content.

## Files changed

- `resources/js/components/operations/pushsale/SaleWorkspaceTable.jsx`
- `resources/js/lib/uiShellStyles.js`
- `resources/css/pushsale-operations-polish.css`

## Deploy

Run normal PNPM deploy:

```bash
pnpm install --frozen-lockfile
pnpm run build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Then hard refresh the browser.
