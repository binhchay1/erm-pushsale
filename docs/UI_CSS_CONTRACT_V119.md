# UI/CSS contract v119

## Nguyên tắc

- CSS cuối cùng của Pushsale shell nằm ở `resources/css/pushsale-unified-page-shell-contract.css`.
- Các page mới nên dùng block header chung:
  - `.ps-page-header-v119`
  - `.ps-page-header-main`
  - `.ps-page-title`
  - `.ps-page-primary-filters`
  - `.ps-page-advanced-filters`
- Select mới dùng `PushsaleSelect` / `PushsaleMultiSelect`; hạn chế trộn native select, ProductSearchSelect và select tự chế trong cùng một header.

## Header chuẩn

Cấu trúc khuyến nghị:

```jsx
<form className="ps-page-header ps-page-header-v119">
  <div className="ps-page-header-main">
    <div className="ps-title ps-page-title">Tên trang</div>
    <div className="ps-page-primary-filters">...</div>
  </div>
  {advancedOpen && <div className="ps-page-advanced-filters">...</div>}
</form>
```

## Menu hover

Không viết thêm rule broad kiểu `.sidebar-menu .a2:hover { ... }` ở page CSS. Nếu cần sửa menu, sửa trong block cuối của `pushsale-unified-page-shell-contract.css` để tránh cascade đè qua lại.
