# v115 - Menu 5.2.1 Danh sách kho parity

## Scope
- Rebuild `/admin/warehouses` / menu 5.2.1 to match the provided Pushsale warehouse-list HTML sample:
  - One clean top search row with title + Province/District/Manager/Search.
  - Only the `Thêm` button is visible in the toolbar.
  - Table action column uses exactly 3 icons: edit, shipping account config, delete.
  - Action column width is fixed so it no longer looks smaller than other cells.
- Rebuild Add/Edit warehouse modal:
  - Title is `Thêm mới kho` for create and `Cập nhật kho` for edit.
  - Form layout follows the sample: left/right two-column fields, two-level address checkbox, default delivery provinces links.
- Add real warehouse-level shipping account configuration:
  - New dialog opened by the bank icon, titled `CẤU HÌNH TÀI KHOẢN GIAO HÀNG CỦA KHO [...]`.
  - Provider tabs and default provider/service config are populated from `config/shipping_partners.php`.
  - Data is saved through `PUT /admin/warehouses/{warehouse}/shipping-account`.
- Keep menu 1.6 pointing to unit feature settings, not system settings.

## Run
```bash
php artisan optimize:clear
php artisan migrate --force
php artisan erm:repair-schema-contract
pnpm build
php artisan erm:test-all --route-smoke --smoke-limit=80 --json
```
