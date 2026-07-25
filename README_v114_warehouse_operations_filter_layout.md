# v114 - Warehouse operations filter layout

Base: v113 `erm-pushsale-v113-docs-menu-select-upload-fix.zip`.

## Fixes
- Align `/admin/warehouse/operations` top search/header row: title remains left, hide-zero checkbox + search cluster move to the right with a consistent right gutter.
- Normalize warehouse filter grid gutters and columns so rows are neat and aligned.
- Align delivery-status quick filters with the filter grid above and spread items evenly across the row.
- Keep table gutter aligned with filter/status sections.

## Deploy/test
```bash
cd /var/www/erm-pushsale
php artisan optimize:clear
pnpm build
php artisan erm:test-all --route-smoke --smoke-limit=80 --json
```

Check: `/admin/warehouse/operations`.
