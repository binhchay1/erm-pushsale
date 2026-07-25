# v131 — Header alignment + sidebar hover runtime contract

## Scope
- Fix `2.4.3 Duyệt kết nối dữ liệu` header: title and filters share one row, search button no longer drops to a second row.
- Fix `2.1 Marketing dashboard` top title spacing: title starts near the left page gutter; filters stay compact and two-row.
- Fix AdminLTE sidebar second-level hover once at runtime and canonical CSS level.

## Contract
- `resources/css/pushsale-adminlte-canonical-contract.css` remains the last global CSS file.
- Sidebar second-level hover is now enforced by both:
  - canonical CSS selectors, and
  - imperative runtime hover guard in `resources/js/components/layout/AppSidebar.jsx` using `style.setProperty(..., 'important')`.
- Do not add new page-specific files to fix sidebar hover. Change `AppSidebar.jsx` or the canonical contract only.

## Test points
- `/admin/marketing/landing-approvals`
- `/admin/marketing/dashboard`
- any page with sidebar open, hover second-level menu items that have no third-level flyout.
