# v116 — Warehouse 5.2.1 alignment, action icons, shipping dialog

Base: v115.

Changes:
- Align `/admin/warehouses` table start with the page title/add button.
- Normalize action icons with a reusable `.ps-action-icon-row` / `.ps-action-icon` CSS contract.
- Keep 3 warehouse row actions on one line: edit, shipping-account config, delete.
- Widen and rebalance the shipping account dialog so provider tabs + form fields fit without broken horizontal overflow on common desktop widths.

Deploy:
```bash
cd /var/www/erm-pushsale
php artisan optimize:clear
php artisan migrate --force
php artisan erm:repair-schema-contract
pnpm build
php artisan erm:test-all --route-smoke --smoke-limit=80 --json
```

Quick QA:
- /admin/warehouses
- open row action: Cấu hình tài khoản giao hàng
- verify action icons are same baseline and dialog form fields fit.
