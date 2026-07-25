# v132 Marketing leader report filter + sidebar hover fix

- Rebuilt `/ld/marketing/thong-ke-truong-nhom` header/filter into one stable Pushsale-style header: title left, primary filters right, advanced filters on one aligned row.
- Removed redundant info/help icon and header collapse icon from this report.
- Added final sidebar hover contract in `pushsale-adminlte-canonical-contract.css` and DOM pointerover guard in `AppSidebar.jsx` so second-level items without third-level submenu do not show white hover overlays or thin blue top borders.

Run:

```bash
php artisan optimize:clear
pnpm build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
