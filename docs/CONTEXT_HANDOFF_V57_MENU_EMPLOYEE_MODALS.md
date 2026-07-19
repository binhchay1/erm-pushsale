# V57 - Menu flyout + employee account modals

## User rule reinforced
- Pushsale route in screenshots is only for identifying the original screen.
- The visible UI/UX and visible feature behavior should match Pushsale.
- Backend/routes/services stay ERM-native and role/permission-scoped.
- Do not copy raw iframe HTML from Pushsale into the project. Rebuild the visible behavior as React/Inertia components and Laravel controller actions.

## Menu fixes
- `resources/js/components/layout/AppSidebar.jsx`
  - Third-level flyout now closes on mouse leave, outside click/touch, viewport scroll/resize, route change, or when hovering a second-level item without children.
  - Flyout keeps itself open only while the pointer is over its parent or the portal flyout.
- `resources/css/pushsale.css`
  - Added final V57 contract block for menu colors, typography, spacing, flyout hover, border radius, shadow and slower animation.
  - Sidebar no longer has its own scrollbar; it uses the page scroll behavior closer to Pushsale's AdminLTE sidebar.

## Employee account modals
- `resources/js/pages/Admin/Users/Index.jsx`
  - Replaced old create-page links from the two toolbar buttons with modal flows:
    - `SingleAccountModal` for one account, width 600px.
    - `BulkAccountModal` for many accounts, width 800px.
  - UI follows the Pushsale popup fields: role, account, password, phone, full name, employee code, email, base salary, shifts, team leader, receive data.
- `app/Http/Controllers/Admin/UserController.php`
  - Index now passes `workShifts` to the list page.
  - Added `storeBulk()` to create multiple accounts from textarea lines.
- `routes/web.php`
  - Added `POST /admin/users/bulk` before the resource route.
- `app/Support/PermissionMap.php`
  - Added `admin.users.bulk.store => hr:full`.

## Build
- Ran `npm ci --offline` to restore local node_modules.
- Ran `npm run build` successfully.
