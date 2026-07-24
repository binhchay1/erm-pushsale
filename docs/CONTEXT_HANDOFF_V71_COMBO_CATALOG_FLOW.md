# V71 - Combo catalog flow

## Scope

- Rebuild page `1.3.2 Danh sách combo` as a dedicated React page instead of the generic captured dialog renderer.
- Split combo flow into two clear dialogs:
  - `Thêm combo`
  - `Cập nhật combo`
- Keep the current Pushsale shell/menu/frontend base from V69/V70.

## Frontend

- `resources/js/pages/Pushsale/Pages/Page_1_3_2.jsx`
  - Dedicated combo page.
  - Fixed header/search/filter/action layout.
  - Working setting menu with export/reload.
  - Real table columns: mã combo, tên combo, sản phẩm trong combo, tổng SP, giá gốc, giá combo, ưu đãi, trạng thái, cập nhật, thao tác.
  - No legacy inline dialog HTML injection for combo edit/create.
- `resources/css/pushsale-combo-page.css`
  - Page-specific CSS for combo table, filter, heading and dialogs.
  - Keeps table inside viewport and avoids horizontal scroll.
- `resources/js/lib/uiShellStyles.js`
  - Loads `pushsale-combo-page.css` after V70 polish CSS.

## Backend

- `app/Services/Pushsale/PageResourceManager.php`
  - Supports `component_items` with product id, quantity and unit price.
  - Validates combo must include at least one simple product.
  - Prevents nesting combo inside combo.
  - Keeps combo SKU immutable after create.
- `app/Services/Pushsale/PushsalePageService.php`
  - Combo rows now return real component labels and `_form.component_items` for update dialog.
- `config/pushsale_resources.php`
  - Combo resource validates `component_items`.
- `config/pushsale_pages.php`
  - Combo schema columns include component/product detail and actions.

## Notes

- Existing routes are reused:
  - `POST /admin/catalog/combos/records`
  - `PUT /admin/catalog/combos/records/{id}`
  - `DELETE /admin/catalog/combos/records/{id}`
- No backend demo/static combo data is introduced.
