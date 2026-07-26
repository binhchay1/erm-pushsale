# CONTEXT HANDOFF V98 — Warehouse 5.3 nhập/xuất kho

## Phạm vi

V98 xử lý lại 3 menu con cấp 3 của **5.3 Nhập, xuất kho** theo template `template-ten-1`:

- `5.3.1 Phiếu nhập / xuất kho` — `/admin/warehouse/vouchers/entry`
- `5.3.2 Danh sách phiếu xuất/nhập kho` — `/admin/warehouse/vouchers`
- `5.3.3 Lịch sử nhập / xuất kho (Thẻ kho)` — `/admin/warehouse/movement-history`

## Frontend/UI

- Rebuild lại `public/pushsale-templates/5.3.1.html`, `5.3.2.html`, `5.3.3.html` từ HTML capture thật.
- Giữ header, filter, form, table header, notice, action buttons theo Pushsale.
- Thêm CSS scoped `resources/css/pushsale-warehouse-flow-contract.css` và đăng ký trong `pushsaleStyleRegistry`.
- CSS chỉ scope theo `.pushsale-page[data-page-code^="5.3"]`, không ghi đè các báo cáo/menu/dashboard đã làm ở V90–V97.
- Table 5.3 dùng `data-pushsale-grid-anchor="primary"`, dữ liệu render từ backend thật, không dùng row mẫu trong HTML.

## Backend/business

- `PageResourceManager::createWarehouseVoucher()` đã chuyển sang transaction.
- Khi tạo phiếu kho:
  - tạo `warehouse_vouchers`
  - tạo `warehouse_voucher_lines`
  - cập nhật `warehouse_inventories`
  - tạo `warehouse_inventory_movements`
  - link thẻ kho về phiếu bằng `reference_type = warehouse_voucher`, `reference_id = voucher.id`
- Nếu xuất kho thiếu tồn, transaction rollback, không còn tình trạng đã tạo phiếu nhưng không trừ được kho.
- `PushsalePageService` đổi 5.3.2 sang đọc từ `warehouse_vouchers` thật thay vì group movement rời rạc.
- 5.3.3 đọc movement thật và hiển thị mã phiếu gốc nếu movement sinh từ phiếu kho.

## Demo data

- `InventorySeeder` giờ tạo thêm voucher + voucher line cho dữ liệu nhập đầu kỳ/xuất thủ công.
- Các màn 5.3.1, 5.3.2, 5.3.3 có cùng dữ liệu liên kết, không bị rỗng hoặc lệch nhau.

## Test mới

`tests/Feature/Warehouse/WarehouseVoucherBusinessLinkTest.php`

Coverage:

1. Tạo phiếu nhập thủ công sinh voucher, line, tồn kho và movement có reference về voucher.
2. Phiếu xuất thiếu tồn rollback đúng, không tạo voucher mồ côi.
3. Ba trang 5.3 cùng nhìn thấy một dữ liệu nghiệp vụ liên kết.

## Ghi chú kỹ thuật

- V98 không thay đổi flow sale/accounting/report V90–V97.
- Không thêm global selector CSS mới.
- Không sửa thứ tự menu ngoài nhóm 5.3.
