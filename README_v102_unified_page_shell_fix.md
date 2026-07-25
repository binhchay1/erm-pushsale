# V102 - Unified Pushsale page shell, seed coverage, and route cleanup

## Main target

This patch fixes the page chrome mismatch shown on `/admin/warehouse/vouchers` compared with Pushsale's `/ld/warehouse/danh-sach-phieu-xuat-nhap-kho`.

The old app allowed each imported/template page to keep its own header row, filter row, title alignment, spacer, and table wrapper. That made pages look different depending on legacy HTML and legacy numbered files. This patch adds one final shared contract layer that normalizes the same structure for all Pushsale pages at runtime.

## Important files

- `resources/js/pages/Pushsale/BusinessPage.jsx`
  - Adds runtime normalization classes for legacy template headers, filter rows, composite filter/table rows, nested template wrappers, and empty spacer nodes.
- `resources/css/pushsale-unified-page-shell-contract.css`
  - Final shared page shell CSS loaded last.
  - Normalizes page header, title, search/action area, filter rows, table wrappers, and page scrolling.
- `resources/js/lib/pushsaleStyleRegistry.js`
  - Loads the final CSS contract last.
- `app/Http/Controllers/Admin/Pushsale/Warehouse/WarehouseVoucherListController.php`
  - Semantic controller for menu/page `5.3.2` voucher list.
- `app/Http/Controllers/Admin/Pushsale/Warehouse/WarehouseVoucherEntryController.php`
  - Semantic controller for menu/page `5.3.1` voucher entry.
- `routes/pushsale_pages.php`
  - Routes warehouse voucher pages through the semantic controllers above.
- `tests/Feature/Pushsale/PushsaleMenuDemoCoverageTest.php`
  - Seeds the application and verifies configured menu pages can return rows/meta through the real `PushsalePageService`.
- `tests/Unit/PushsaleUnifiedShellContractTest.php`
  - Verifies the final page shell CSS is loaded after older page CSS and that the runtime normalizer markers exist.
- `scripts/audit-pushsale-page-shell.mjs`
  - Audits configured Pushsale pages for template/header availability and flags remaining legacy-numbered files.
- `scripts/audit-pushsale-route-semantic-names.mjs`
  - Audits route definitions so new routes do not expose menu-number/page-number naming.
- `docs/PUSHSALE_PAGE_SHELL_REFACTOR_V102.md`
  - Handoff notes for the next context window.

## Commands to run after applying patch

```bash
composer install
pnpm install
php artisan optimize:clear
php artisan migrate --seed
php artisan test --filter=PushsaleUnifiedShellContractTest
php artisan test --filter=PushsaleMenuDemoCoverageTest
node scripts/audit-pushsale-route-semantic-names.mjs
node scripts/audit-pushsale-page-shell.mjs
pnpm build
```

## Notes

Legacy `Page_*` React components and `Page*_Controller` controllers are still present in this patch. They are intentionally not deleted in one sweep because many page keys still map to those files. The new pattern is already applied to warehouse voucher pages and documented; the rest should be migrated module-by-module so old files can be deleted safely once no route/config references them.
