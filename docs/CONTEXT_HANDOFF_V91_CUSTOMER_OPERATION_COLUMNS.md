# V91 — Customer profile header + shared operation product/money columns

## Scope

This patch fixes the live QA issues reported after V90:

1. `Hồ sơ khách hàng` header/search area touched the viewport edge and did not align with the common page-header rhythm.
2. Product/quantity/unit-price and money breakdown columns were visually different between:
   - Hồ sơ khách hàng,
   - Sale tác nghiệp,
   - Thủ kho tác nghiệp / đăng đơn,
   - Kế toán đối soát.
3. Upsale rows needed one consistent UI: divider before the first upsale line, a small upsale icon with `title="Upsale"`, and the same column structure everywhere.
4. Add backend test coverage to confirm the same presenter payload feeds all operation surfaces.

## Frontend contract

New shared component:

```text
resources/js/components/operations/OrderLineBreakdown.jsx
```

Exports:

- `isUpsellOrderItem(item)`
- `firstUpsellDivider(items, index)`
- `OrderProductsBreakdown({ items })`
- `OrderMoneyBreakdown({ row })`

This component is now used by:

- `resources/js/pages/Sales/CustomerProfile.jsx`
- `resources/js/components/operations/pushsale/SaleWorkspaceTable.jsx`
- `resources/js/components/operations/WarehouseOrderTable.jsx`
- `resources/js/components/operations/AccountingReconTable.jsx`
- `resources/js/components/operations/OperationOrderTable.jsx` legacy fallback

## CSS contract

New final CSS file:

```text
resources/css/pushsale-v91-customer-operation-money-contract.css
```

Registered in:

```text
resources/js/lib/pushsaleStyleRegistry.js
```

The CSS is scoped under `body.pushsale-app-body` and only targets explicit classes:

- `.ps-customer-profile-page`
- `.ps-order-products-breakdown`
- `.ps-order-money-breakdown`
- `.ps-order-products-row`
- `.ps-order-upsale-icon`

It does not use raw global `table`, `td`, `th`, or `.btn` selectors.

## Backend contract

`OrderOperationPresenter::toArray()` remains the single payload contract for Sale/Warehouse/Accounting operation pages and is also reused by `CustomerProfileService`. It already exposes:

```php
'products' => [
    [
        'itemId' => ...,
        'productName' => ...,
        'itemType' => ...,
        'origin' => ...,
        'isUpsell' => ...,
        'quantity' => ...,
        'unitPrice' => ...,
    ],
]
```

This is the data source consumed by the shared frontend component.

## Test coverage

Added:

```text
tests/Feature/Operations/OrderOperationPresenterProductBreakdownTest.php
```

The test asserts:

- Main item stays `isUpsell=false`.
- Upsale item from `item_type=upsell` and `origin=landing_upsell` stays `isUpsell=true`.
- Quantity/unit price/subtotal/discount/VAT/shipping fee/total are kept in the presenter payload.
- `CustomerProfileService` returns the same presenter product contract.

## Validation run in sandbox

```bash
php -l app/Services/Operations/OrderOperationPresenter.php
php -l app/Services/Customers/CustomerProfileService.php
php -l tests/Feature/Operations/OrderOperationPresenterProductBreakdownTest.php
node ./scripts/audit-pushsale-contract.mjs
```

Result:

```text
33 pass, 13 warn, 0 fail
```

The warnings are pre-existing CSS/naming debt from earlier versions.

## Commands to run after deploy/apply

```bash
corepack enable
pnpm install --frozen-lockfile
pnpm build
php artisan test --filter=OrderOperationPresenterProductBreakdownTest
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
