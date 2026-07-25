# AI screenshot access for staging

Use this only for staging/test screenshots. It enables the existing auto-admin middleware so a browser agent can open admin pages and capture annotated screenshots for `/docs` without stopping at the login screen.

## Enable

```bash
APP_DIR=/var/www/erm-pushsale \
DOMAIN=salesloop.vn \
ERM_AUTO_ADMIN_LOGIN_EMAIL=admin@saleops.local \
bash deploy/enable-ai-screenshot-access.sh
```

## Disable immediately after screenshots

```bash
APP_DIR=/var/www/erm-pushsale bash deploy/disable-ai-screenshot-access.sh
```

## Suggested capture prompts

1. Open `/admin/dashboard`, capture the KPI strip and mark revenue, orders waiting, and new data.
2. Open `/admin/marketing/landing-connections`, mark webhook URL, landing source, product mapping, budget, and approval status.
3. Open `/admin/sales/operations`, mark customer phone, operation result, note field, and close-order action.
4. Open `/admin/warehouse/vouchers/entry`, mark voucher type, warehouse, product, quantity, complete button, and stock-add action.
5. Open `/customers`, mark address, message, purchase history, telesale history, internal notes, and Pancake chat.
