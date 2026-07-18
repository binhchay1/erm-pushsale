# V37 — UI shell/auth/sale workspace fix

## Scope
- Restore normal public home/login flow: remove `AutoLoginAsAdmin` from web middleware and disable auto-login in staging helper script/examples.
- Fix `/sales/workspace` SQLSTATE[HY093] caused by tab-count query reusing a repository query with `withCount()` select bindings.
- Add final Pushsale shell CSS override to normalize AdminLTE/Tailwind/Radix conflicts.
- Slow and smooth sidebar open/close and root menu expansion.
- Restore sidebar/flyout backgrounds, text colors, box shadows, and active states closer to Pushsale legacy UI.
- Make floating action buttons fixed circular buttons; prevent them jumping when DevTools/resizing changes viewport.
- Remove empty sale-stage spacer when the tab list is empty.
- Add English translation keys for new Pushsale report controls/labels and wire the source components to i18n.
- Patch currently built CSS/JS assets used by `public/build/manifest.json` so this package works even before a fresh Vite build.

## Main files changed
- `bootstrap/app.php`
- `deploy/staging-enable-test-mode.sh`
- `.env.auto-admin.example`
- `.env.staging-test.example`
- `app/Services/Operations/SaleOperationService.php`
- `resources/css/pushsale.css`
- `public/build/assets/pushsale-VZglJWi2.css`
- `resources/js/components/reports/PushsaleReportChrome.jsx`
- `resources/js/pages/Reports/ExtraReport.jsx`
- `resources/js/i18n/locales/en/reports.js`
- `resources/js/i18n/locales/vi/reports.js`
- `public/build/assets/PushsaleReportChrome-Brgt8ewz.js`
- `public/build/assets/ExtraReport-DZh-Nj4-.js`

## Sale workspace SQL fix
`SaleOperationService` no longer clones the repository `queryFiltered()` query for tab counts. That query contains eager loads and `withCount('pendingSupplementPackets')`; clearing columns without clearing select bindings made PDO bind `false/true` from the subquery into `whereBetween`, producing `BETWEEN 0 AND 1` and `SQLSTATE[HY093]`.

V37 builds tab counts from a clean `Order::query()->applyReportFilter($baseFilter)` query.

## Validation run in sandbox
- PHP lint for `app`, `routes`, `config`, `database`: PASS
- `node --check public/build/assets/PushsaleReportChrome-Brgt8ewz.js`: PASS
- `node --check public/build/assets/ExtraReport-DZh-Nj4-.js`: PASS
- `node --check public/build/assets/AppLayout-C6T4YjHa.js`: PASS
