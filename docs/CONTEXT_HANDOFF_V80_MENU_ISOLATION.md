# V80 - Menu isolation guard

## Scope
- Fix regression where V79 content-shell/page CSS affected the legacy Pushsale sidebar menu visuals.
- Do not touch page content, tables, filters, pagination, or floating actions.

## Changes
- Added `resources/css/pushsale-v80-menu-isolation.css`.
- Loaded it after V79 in `resources/js/lib/uiShellStyles.js`.

## Design rule
The sidebar/flyout now has a hard CSS boundary:
- `.pushsale-main-sidebar` owns root and level-2 menu CSS.
- `.pushsale-third-menu` owns portaled level-3 flyout CSS.
- No generic `.button`, `.btn`, `.content`, `.page`, or table selectors are used.

## Why
V79 correctly introduced a shared content shell, but it was loaded at the end of the cascade. Some older menu primitives depended on earlier cascade order and lost hover/background behavior. V80 restores only menu behavior with high-specificity, scoped rules rather than editing global CSS again.
