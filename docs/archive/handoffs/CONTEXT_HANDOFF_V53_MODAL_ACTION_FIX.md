# V53 — Customer profile modal/action hardening

Scope: customer profile page (`/admin/marketing/customers`, `/admin/sales/customers`, `/customers`) and shared Pushsale modal shell.

Rules preserved:
- Pushsale is only the UI/UX reference. Routes stay role/business scoped.
- Visible actions on a role-scoped page must call the same role-scoped URL family. `/admin/marketing/customers` actions must not silently call `/admin/customers` unless the page itself is `/admin/customers`.
- Modal/dialog placement must be viewport based, not sidebar/content-wrapper based.

Changes:
- `PushsaleModal` now renders a viewport overlay through a body portal with inline layout constraints. This avoids the long-standing left/top drift caused by legacy modal CSS and scroll/transformed wrappers.
- Customer profile floating actions use a single `ps-floating-action-menu` contract and round `ps-action-bubble` buttons. Tooltips are rendered as real child spans with high z-index instead of fragile `::after` labels.
- Export actions use `fetch` + Blob download so a server error never navigates the whole app to a 500 page. The user gets a toast message instead.
- Added role-scoped aliases for `/admin/marketing/customers/*` and `/admin/sales/customers/*` bulk/export actions.
- `CustomerProfile` now derives `apiBase` from `routeUrl` instead of hardcoding `/admin/customers`.

Next checks:
- Open Tin nhắn, Lịch sử xem, Lịch sử mua hàng, Lịch sử tác nghiệp from customer profile; all must be centered.
- Open the floating action menu; all child buttons must be circular and fixed bottom-left.
- Export variants should download CSV or show a toast, not navigate to an error page.
