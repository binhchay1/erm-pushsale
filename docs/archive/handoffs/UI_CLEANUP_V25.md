# V25 — Clean Pushsale UI Contract

V25 removes the stacked internal CSS approach introduced by previous versions. The internal ERM shell now uses exactly one source stylesheet:

- `resources/css/pushsale.css`

All old versioned/internal Pushsale CSS files were removed from `resources/css` so new fixes are not appended on top of conflicting rules.

Public pages and login still use `resources/css/public.css`; they are not affected by the ERM internal shell.

## Contract

- Header: fixed 50px, Pushsale blue.
- Hamburger: 42px, no leftover collapsed gutter.
- Sidebar: 252px when open, fully hidden when collapsed.
- Content: starts at sidebar width only when sidebar is open; starts at `left: 0` when collapsed.
- Font: Arial across the internal app.
- Tables: Pushsale blue header, square cells, no action-cell nested borders.
- Forms: 30px inputs/selects/buttons.
- Modals: fixed viewport center, header/body/footer structure, body scrolls instead of overflowing screen.

## Deployment note

The built CSS file referenced by `public/build/manifest.json` was patched too:

- `public/build/assets/pushsale-VZglJWi2.css`

Run `npm ci && npm run build` on the server when dependencies are available to regenerate hashed assets normally.
