# V50 - Customer profile table parity

Scope: Hồ sơ khách hàng only.

Changes:
- Removed the 2px blue `border-top` from `.ps-customer-titlebar` without touching other page chrome.
- Added Pushsale-like page/table gutters for customer profile table: 14px left/right and top gap.
- Overrode the old global table min-width on the customer profile page only. The table now uses `width: 100%` + fixed percentage columns on desktop, so it does not show horizontal scroll at normal desktop width.
- Kept horizontal scroll fallback only under 1500px viewport.
- Narrowed product and money split-stack internals only inside customer profile to avoid nested content forcing table overflow.

Important:
- Do not re-add page-specific `min-width: 2840px` to `.ps-customer-profile-table`.
- Generic sale/warehouse report table sizing is intentionally untouched.
