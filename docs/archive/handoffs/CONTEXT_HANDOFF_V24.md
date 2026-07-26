# CONTEXT HANDOFF V24 — Menu regression and 500 guard

## What was fixed

- The sidebar regression from V22/V23 was caused by multiple final sidebar strategies fighting each other:
  - collapsed 42px sidebar,
  - overlay sidebar,
  - content-left 42px override.
- V24 adds one final CSS contract loaded last: `resources/css/pushsale-system-contract.css`.
- Desktop behavior is now deterministic:
  - menu opens by default;
  - content starts after the full 252px sidebar;
  - hamburger toggles the menu;
  - collapsed state has no empty 42px content gutter;
  - sidebar does not auto-collapse on every Inertia navigation.
- The header/hamburger is kept at 42px x 50px and the logo area is 252px when open.
- Top-level menu items are bold, child menu items use 12px Arial.

## Backend 500 guard

Static audit found one Inertia route rendering a component that did not exist in the built pages:

- `Admin/ActivityLogs/Show`

The activity log index already opens details in a modal, so `ActivityLogController@show` now redirects back to the index with `focus=<id>` instead of rendering a missing production component. This prevents a manifest/page-resolution 500 when someone opens `/admin/activity-logs/{id}`.

## Validation

- PHP syntax lint on app/config/database/routes/tests: PASS.
- Controller import scan: no missing controller classes from `routes/web.php`.
- Inertia render scan: no missing `resources/js/pages/**/*.jsx` components.

## Deploy notes

Run:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan horizon:terminate
npm ci
npm run build
```

The existing public build CSS/JS was also patched in-place so the package is usable before a rebuild, but a production rebuild is still recommended.
