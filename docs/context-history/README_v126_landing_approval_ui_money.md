# v126 — Landing connections table + approval money UI

## Scope

- Polish menu 2.4.1 landing connection table to match Pushsale source layout more closely.
- Keep the new business flow: source creation first, product/package and budget approval later.
- Polish menu 2.4.3 approval table and dialog.

## Business contract

1. `/admin/marketing/landing-connections` creates or edits landing/website/facebook source records.
2. Source creation does not require product/package.
3. Source creation does not publish or sync `marketing_sources`.
4. `/admin/marketing/landing-approvals` attaches product/package and budget.
5. Approval syncs to legacy `marketing_sources` for existing lead/report compatibility.
6. The table checkboxes `Nhập TC` and `Duyệt` are display values. `Duyệt` in source creation means "request approval"; final approval remains on menu 2.4.3.

## UI contract

- Marketing column is wider; source column is tighter.
- Secondary source/channel text uses Pushsale purple tone.
- URL connection V2 field is borderless until focus/double-click.
- `Nhập TC` and `Duyệt` columns are narrow content columns.
- Approval budget input is a VNĐ text field and submits a sanitized integer.
- Approval page filter has explicit search button.

## Deployment

Run:

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan erm:repair-schema-contract
pnpm build
php artisan erm:test-all --route-smoke --smoke-limit=30 --json
```
