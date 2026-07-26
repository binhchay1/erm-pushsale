# V83 - Universal page contract and legacy template header adapter

## Scope

V82 introduced the shared `PushsalePageShell` but did not affect many legacy HTML pages rendered through `PushsaleBusinessPage` because those pages inject raw templates into `.pushsale-template-host` and bypass the React adapters.

V83 adds a final scoped CSS authority and a tiny host marker for legacy templates.

## Changes

- Adds `resources/css/pushsale-universal-page-contract.css`.
- Adds `pushsale-template-host-v83` marker in `normalizeTemplateLayout()`.
- Loads V83 after V82 in `resources/js/lib/uiShellStyles.js`.
- Restores level-2/level-3 menu background and hover strictly inside `.pushsale-main-sidebar` / `.pushsale-third-menu`.
- Normalizes legacy template headers under `.pushsale-page .pushsale-template-host` without touching sidebar styles.
- Keeps product/combo search as a select surface; search stays inside dropdown.

## Rule

Do not add broad global CSS selectors such as `button`, `.btn`, `table`, `.form-control`, `.content-wrapper` in page-specific CSS. Use one of:

- `.pushsale-main-sidebar` for menu only.
- `.ps-page-shell` for React page chrome.
- `.pushsale-page .pushsale-template-host` for old Pushsale template pages.
- A page-specific root class for custom pages.
