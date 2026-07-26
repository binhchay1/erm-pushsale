# V42 - Warehouse CSS scope fix

## Root cause
V41 introduced a scoped warehouse UI block with selectors such as `body.pushsale-app .ps-wh-legacy-page`, but the Laravel/Inertia shell actually mounts the admin layout on `body.pushsale-app-body`.

Because of that one selector mismatch, almost every V41 warehouse layout rule did not apply in the browser. The JSX rendered all controls, but CSS fell back to older generic `.ps-wh-*` rules, causing:

- title/checkbox/search stacking vertically;
- filter grid expanding into 2 uneven columns with huge right fields;
- floating action button appearing in the wrong place;
- table width/cell styles partly reverting to old behavior.

## Fix
- Replaced `body.pushsale-app ...` with `body.pushsale-app-body ...` in `resources/css/pushsale.css`.
- Applied the same replacement to the built CSS asset `public/build/assets/pushsale-DalFkY5B.css` so deploys without Vite rebuild still get the fix.
- Did not create a new warehouse CSS file.
- Did not copy/paste raw Pushsale HTML into a new file.
- Kept the V41 component split:
  - `WarehouseFilterPanel.jsx`
  - `WarehouseOrderTable.jsx`
  - `Operations.jsx`

## Files changed
- `resources/css/pushsale.css`
- `public/build/assets/pushsale-DalFkY5B.css`
