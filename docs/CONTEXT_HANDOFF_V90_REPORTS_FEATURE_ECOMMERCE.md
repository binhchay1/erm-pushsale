# V90 — Report shell, feature settings wiring, ecommerce connect-shop

## Scope

This pass continues the V85–V89 shared-shell cleanup. It focuses on the live QA issues reported after V89:

- CEO V2 report header/filter layout and the large blank gap between sale/marketing tables.
- Warehouse revenue and sale work reports using inconsistent header/pagination structure.
- Feature settings sidebar duplicate `Tất cả` and weak tab typography.
- Shipping partner configuration page overflowing without page scroll.
- Feature settings that must affect the real order/warehouse/shipping business flow.
- Menu `2.9.1 Danh sách kết nối cửa hàng` rebuilt from the Pushsale `connect-shop-list` reference HTML instead of the old placeholder/integration list.

## Files changed

### CSS/UI contract

- `resources/css/pushsale-report-feature-ecommerce-contract.css`
  - shared report toolbar spacing
  - shared report pagination rhythm
  - CEO V2 table height/gap fix
  - sale work top pager cleanup
  - feature settings tab polish
  - shipping config scrollability
  - product/admin page edge padding
  - ecommerce connect-shop table/header styling
- `resources/js/lib/pushsaleStyleRegistry.js`
  - registers the V90 final contract CSS

### Reports

- `resources/js/components/reports/ceo/CeoReportFilterBar.jsx`
  - migrated from legacy `m-header-wrap`/manual columns into `PushsalePageShell`
  - title, primary filters, advanced filters and actions now follow the same shell contract
- `resources/js/pages/Reports/ExtraReport.jsx`
  - common reports use `PushsalePageShell`
  - sale work report only shows the top count/pager when there is more than one page
  - bottom pager also hides for one-page data
- `resources/js/components/reports/PushsaleReportChrome.jsx`
  - `PushsalePager` now carries `ps-pagination-v81 pushsale-pagination` so all pages can be styled by the shared pagination contract

### Feature settings/business wiring

- `resources/js/pages/Settings/Index.jsx`
  - removed duplicate static `Tất cả` label from the sidebar
- `app/Services/Orders/OrderClosingService.php`
  - applies `SettingGhiChuGiaoHangSale` as default shipping note when sale closes an order without a note
- `app/Http/Controllers/Admin/ShippingOrderController.php`
  - blocks create/cancel shipment by role according to:
    - `SettingKhoDangDon`
    - `SettingKhoHuyDangDon`
    - `SettingKeToanDangDon`
    - `SettingKeToanHuyDangDon`
- `app/Services/Shipping/CreateShipmentService.php`
  - applies `SettingDangDonNguoiNhanSDT` to the outbound carrier payload without overwriting the customer's phone stored in SaleOps
  - falls back to `SettingGhiChuGiaoHangSale` when a shipping note is missing before creating a waybill
- `app/Services/Warehouse/WarehouseOrderActionService.php`
  - blocks warehouse product edits when `SettingKhoSuaSanPham` is off
  - blocks warehouse shipping/recipient/provider edits when `SettingGiaoVanCapNhatPTGH` is off
  - blocks manual cancel-closing by warehouse/accounting when their cancel setting is off

Existing wiring already present before V90:

- `app/Services/Orders/OrderCodeGenerator.php`
  - uses `SettingMaDonPrefix` when creating the final order code after sale closes an order

## Ecommerce 2.9.1

- `app/Http/Controllers/Admin/EcommerceConnectShopController.php`
- `resources/js/pages/Admin/Ecommerce/ConnectShops.jsx`
- `routes/web.php`
- `config/pushsale_navigation.php`
- `resources/js/i18n/locales/vi/pages.js`
- `resources/js/i18n/locales/en/pages.js`

Menu entries `1.15.1` and `2.9.1` now point to `/admin/ecommerce/connect-shops`.
Legacy Pushsale URLs redirect to the new screen:

- `/connect-shop-list`
- `/ld/ecommerce/e-connect-shop-list`

The screen follows the Pushsale reference structure:

- title: `Danh sách kết nối sàn thương mại điện tử`
- filters: platform, warehouse, keyword `Tên hoặc ID shop`
- table columns: STT, Loại sàn, Tên Kho, Id Shop, Tên shop, Logo, Ghi chú, Cập nhật, Thêm

## Validation run

```bash
php -l app/Services/Warehouse/WarehouseOrderActionService.php
php -l app/Services/Orders/OrderClosingService.php
php -l app/Http/Controllers/Admin/ShippingOrderController.php
php -l app/Http/Controllers/Admin/EcommerceConnectShopController.php
php -l app/Services/Shipping/CreateShipmentService.php
php -l routes/web.php
php -l config/pushsale_navigation.php
git diff --check
node ./scripts/audit-pushsale-contract.mjs
```

Result:

- PHP syntax checks: pass
- `git diff --check`: pass
- Pushsale contract audit: `33 pass, 13 warn, 0 fail`

The sandbox does not contain `vendor` or `node_modules`, so `php artisan route:list`, PHPUnit and `pnpm build` must be run after dependencies are installed on the project machine/server.
