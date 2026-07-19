# V62 - Dialog-only CSS cleanup

## Scope
- Keep React popup UI on the single Radix `PushsaleDialog` contract.
- Remove remaining `modal` wording/usages from active frontend source under `resources/js` and `resources/css`.
- Fix dialog surfaces that appeared dimmed under the backdrop by separating overlay and content z-index/opacity contracts.
- Move the Sale workspace `TN cần` expanded editor out of table layout with a body portal so hover/focus no longer changes table width or horizontal scroll.
- Re-balance Marketing Dashboard column widths so the normal dashboard table fits the viewport; only advanced UTM mode is allowed to scroll horizontally.

## Files touched
- `resources/js/components/ui/dialog.jsx`
- `resources/js/components/ui/pushsale-dialog.jsx`
- `resources/js/components/operations/pushsale/SaleWorkspaceTable.jsx`
- `resources/js/pages/Admin/Landing/Approvals.jsx`
- `resources/css/pushsale.css`

## Validation
- `npm ci`
- `npm run build`
- Frontend grep: no `modal`/`Modal`/`ps-modal`/`modal-` remains in `resources/js` or `resources/css`.

## Notes
- `public/build` is intentionally ignored and should be produced by the server deploy hook.
- `storage` runtime files stay ignored; only placeholder `.gitignore` files should be tracked.
