# V43 - Pushsale feature settings page

## Scope
Reworked `1.6 Cấu hình chức năng` as a real reusable Inertia page backed by tenant-scoped settings, based on the Pushsale HTML source for `/ld/unit-admin/cau-hinh-chuc-nang`.

## Key files
- `config/pushsale_feature_settings.php`: source-derived definition for tabs, rows, controls, options and default values.
- `app/Services/Settings/FeatureSettingsService.php`: loads/sanitizes/saves tenant-scoped values in `settings` via `AppSetting` key `unit.feature_settings`.
- `app/Http/Controllers/SettingsController.php`: renders feature settings and persists changes.
- `resources/js/pages/Settings/Index.jsx`: reusable component renderer for tabs, search, tables, field types and Excel columns.
- `resources/css/pushsale.css`: scoped `ps-feature-settings-page` layout/styles.
- `app/Services/Orders/OrderCodeGenerator.php`: order-code prefix now respects `SettingMaDonPrefix`.

## Routes
- `/settings`
- `/ld/unit-admin/cau-hinh-chuc-nang`

## Notes
- This is not raw Pushsale HTML pasted into a React file. The HTML source is parsed into structured config and rendered by shared React components.
- It persists backend data through `AppSetting`, scoped per tenant by existing `TenantManager` logic.
- Excel column configs are represented as reorderable/removable chips with selectable columns and are stored as arrays.
- Activity log entry `settings.feature_settings_updated` is written on save.
