# Pushsale page shell refactor V102

## Mục tiêu

Tất cả trang quản trị phải đi qua một contract giao diện duy nhất:

1. Topbar xanh cố định của app.
2. Header trắng của trang: title bên trái, search/action bên phải.
3. Filter/body nằm dưới header, không được tạo thêm khoảng trắng/`content-wrapper` lồng nhau.
4. Bảng dài dùng scroll ngang ở chính vùng bảng; trang dài dùng scroll dọc của browser.

## Cách xử lý file cũ

Các file theo số menu `Page_5_3_2.jsx`, `Page5_3_2Controller.php` đang được giữ để không vỡ route cũ, nhưng không được dùng làm pattern mới. Luồng mới đặt tên theo nghiệp vụ:

| Menu | Route | Controller mới |
| --- | --- | --- |
| 5.3.1 Phiếu nhập/xuất kho | `/admin/warehouse/vouchers/entry` | `Admin\Pushsale\Warehouse\WarehouseVoucherEntryController` |
| 5.3.2 Danh sách phiếu xuất/nhập kho | `/admin/warehouse/vouchers` | `Admin\Pushsale\Warehouse\WarehouseVoucherListController` |

Với các menu tiếp theo, chỉ tạo controller/component theo tên nghiệp vụ. Không tạo thêm file `Page_<menu>.jsx` nếu trang đó đã có flow React riêng.

## Contract frontend

`resources/js/pages/Pushsale/BusinessPage.jsx` vẫn load HTML mẫu Pushsale, nhưng sau khi render sẽ normalize DOM:

- `.m-header-wrap` đầu tiên được đánh dấu `pushsale-primary-header-wrap`.
- `.m-header` được đánh dấu `pushsale-header-row`.
- Cột title/action/filter được phân loại bằng class runtime.
- Các `content-wrapper` lồng từ template mẫu bị trung hòa bằng `.pushsale-nested-content-wrapper`.
- Các spacer rỗng, `padding-top` lớn, `height` rỗng bị đánh dấu để CSS final ẩn đi.

CSS final nằm ở `resources/css/pushsale-unified-page-shell-contract.css` và được load cuối trong `pushsaleStyleRegistry.js`.

## Quy tắc seed/test

`DatabaseSeeder` gọi các seed nghiệp vụ theo thứ tự dữ liệu xương sống:
account → catalog/product → inventory → marketing/source → sales/order → warehouse → ecommerce/report.

Test `PushsaleMenuDemoCoverageTest` chạy full seed và kiểm tra các source chính trả dữ liệu qua `PushsalePageService`. Nếu sau này thêm page mới mà test rỗng, phải bổ sung seed nghiệp vụ tương ứng chứ không hard-code HTML demo.

## Audit trước khi chuyển context

Chạy:

```bash
php artisan test --filter=PushsaleMenuDemoCoverageTest
node scripts/audit-pushsale-page-shell.mjs
node scripts/audit-pushsale-route-semantic-names.mjs
```

Audit shell sẽ báo các template còn header lạ hoặc file còn đặt tên theo số menu để dọn dần.
