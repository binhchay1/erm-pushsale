# Pushsale V6 — route and page registry correction

## Root cause of the 404 on every numbered menu page

The page registry uses literal top-level keys such as `1.10`, `2.6.1` and `8.5.17`.
Laravel's `config()` helper treats dots as nested-array separators. The previous code called:

```php
config("pushsale_pages.{$code}")
```

For `1.10`, Laravel tried to read `pushsale_pages[1][10]`, although the real key is
`pushsale_pages['1.10']`. The controller therefore aborted with 404 after the route had
already matched. The same bug existed in `pushsale_resources`, causing create/update
requests to fail after the page was opened.

V6 loads the complete registry first, then performs an exact array-key lookup:

```php
$pages = config('pushsale_pages', []);
$schema = $pages[$code] ?? null;
```

The resource registry now uses the same exact-key strategy.

## Semantic URLs

Menu numbers are metadata only. They are no longer exposed as the primary page URL.
Examples:

- `1.1.1` → `/admin/company/profile`
- `1.2.1` → `/admin/hr/employees`
- `1.3.1` → `/admin/catalog/products`
- `1.10` → `/admin/leads/import`
- `4.2` → `/admin/sales/customers`
- `5.2.2` → `/admin/warehouse/products`
- `8.5.9` → `/admin/reports/power-dashboard`

The old `/admin/pages/...` links remain as permanent redirects so bookmarks do not break.
The complete mapping is in `config/pushsale_routes.php`.

## Company information

`1.1.1 Thông tin` is no longer mapped to personal UI preferences. It now uses:

- `GET /admin/company/profile`
- `PUT /admin/company/profile`
- `App\\Http\\Controllers\\Admin\\CompanyProfileController`
- `resources/js/pages/Admin/Company/Profile.jsx`

The page edits the current tenant company and does not edit the logged-in user's visual
preferences.

## Deployment

After replacing the source, clear both route and config caches before rebuilding them:

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan horizon:terminate
```

`public/build` is included in the release archive.
