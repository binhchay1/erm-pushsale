# V51 Customer profile / action / error polish

## Scope
- Hồ sơ khách hàng: reset button placement, compact table margins from V50 remain intact.
- Floating action menu: one circular Pushsale-like button system for every page that uses `.action-container`.
- Error pages: larger, centered, full viewport, less cramped.
- Customer bulk export: made safer so export callback errors do not render a 500 page.

## Notes
- Do not create a separate CSS file per page for common floating action buttons. Keep the shared rule in `resources/css/pushsale.css` under the V51 block.
- The customer page still has only one visible search button in the top titlebar; the lower search in the expanded filter is intentionally hidden by CSS.
- Reset button is positioned on the last filter row to keep the filter grid visually consistent.
- Error shell has source changes in `resources/js/components/errors/ErrorShell.jsx` and CSS fallback in `resources/css/app.css` for stale built assets.
