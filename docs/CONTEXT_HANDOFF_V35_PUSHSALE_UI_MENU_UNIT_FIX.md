# V35 - Pushsale UI/Menu/Unit Form Fix

## Scope

This patch continues from V34 and focuses on visual parity with the original Pushsale/AdminLTE source template supplied by the user.

## Changed

- Sidebar/menu:
  - Restored light Pushsale/AdminLTE visual style: `#f9fafc` sidebar, source-like text sizing, root/child spacing, active blue child row.
  - Added subtle right-side shadow like the original Pushsale shell.
  - Added smooth slide-in/out for the sidebar.
  - Added smooth expand/collapse animation for root menu groups using `max-height`, opacity, and transform transitions.
  - Kept hamburger pseudo-icon disabled so AdminLTE `sidebar-toggle::before` does not duplicate the explicit FontAwesome bars icon.

- Employee list (`1.2.1`):
  - `Số TK` now comes from the filtered real user query count instead of the pre-filter visible-id count.
  - Fixed Bootstrap/AdminLTE clearfix pseudo-elements becoming CSS grid items, which caused filters to start with a blank column.
  - Re-aligned search/filter rows to match the legacy source layout more closely.

- Unit profile (`1.1.1`):
  - Rebuilt `/admin/company/profile` as a source-faithful AdminLTE page instead of the modern card/grid UI.
  - Layout now matches the original `Thông tin đơn vị` screen: title bar, left labels, 598px inputs, required red markers, checkbox row, and compact Save button.
  - Added persisted legacy fields on `companies`:
    - `product_field`
    - `address_2`
    - `use_two_level_address`
    - `province_name`
    - `district_name`
    - `ward_name`
  - Controller guards reads/writes with `Schema::hasColumn` so deployment remains safe while migrations run.

## Files touched

- `resources/css/pushsale.css`
- `resources/css/pushsale-layout.css`
- `resources/css/pushsale-adminlte-pages.css`
- `public/build/assets/pushsale-VZglJWi2.css`
- `resources/js/pages/Admin/Company/Profile.jsx`
- `public/build/assets/Profile-Do5279ag.js`
- `app/Http/Controllers/Admin/CompanyProfileController.php`
- `app/Http/Controllers/Admin/UserController.php`
- `app/Models/Company.php`
- `database/migrations/2026_07_18_000000_add_legacy_unit_profile_fields_to_companies_table.php`

## Deploy reminder

```bash
cd /var/www/erm-pushsale
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan horizon:terminate
```

If Vite build is not run on the server, this patch still includes the edited built assets for the currently referenced manifest entries.
