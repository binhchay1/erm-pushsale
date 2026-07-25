# v117 — Warehouse address filters + searchable Pushsale selects

Base: v116.

## Fixed
- Warehouse 5.2.1 province/district filters now use searchable Pushsale-style select controls.
- Controller passes a full Vietnam address catalog:
  - legacy 63-province dataset with province -> district -> ward;
  - 2025 two-level dataset with province -> ward.
- Warehouse list filter: selecting province dynamically populates district/ward-style search options.
- Warehouse add/edit dialog:
  - normal mode uses legacy Province -> District -> Ward;
  - "Sử dụng địa chỉ 2 cấp" mode uses 2025 Province -> Ward and disables District.
- Search box inside select now has the magnifier icon and select2-like dropdown styling.

## Test
```bash
cd /var/www/erm-pushsale
php artisan optimize:clear
php artisan migrate --force
php artisan erm:repair-schema-contract
pnpm build
php artisan erm:test-all --route-smoke --smoke-limit=80 --json
```

Manual checks:
- /admin/warehouses
- open province filter and type a province name
- select province and verify district options change
- open add/edit warehouse dialog, toggle "Sử dụng địa chỉ 2 cấp", verify 2025 province/ward mode
