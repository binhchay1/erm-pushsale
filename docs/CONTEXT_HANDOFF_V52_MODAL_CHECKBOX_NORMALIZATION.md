# V52 - Modal/Dialog + Checkbox normalization

## User requirement
- Checkbox labels across pages must not look bold or sit slightly above the checkbox.
- All modals/dialogs in the project must be centered consistently in the viewport.
- Modal CSS must be a shared system rule, not one-off rules per page.

## Implementation
- Added one final shared CSS block to `resources/css/pushsale.css` and patched the current built CSS asset.
- Checkbox contract:
  - labels containing a direct checkbox use `inline-flex`, `align-items:center`, font-weight 400, stable 14px checkbox size.
  - covers known project classes: `ps-sale-check`, `ps-wh-header-check`, `ps-order-checkbox`, `psm-utm-check`, `pssp-checkbox`, `ps-feature-check`, report checkbox fields, table `chk-*` wrappers.
- Modal contract:
  - overlays are `fixed inset:0`, full `100vw/100dvh`, flex centered, not offset by sidebar/content wrapper.
  - Radix `DialogContent` and legacy modal panels are centered by viewport with `left:50vw/top:50dvh` or flex-centered panel.
  - body scrolls inside modal; header/footer stay fixed.

## Important note
Do not reintroduce modal rules that set `left: var(--ps-sidebar-width)` or `align-items:flex-start` on modal overlay classes. If a page needs a custom width, set `--ps-modal-width`, not a new centering system.
