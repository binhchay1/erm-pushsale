# V77 - Accounting operation workspace parity

## Scope

- Rebuild `/admin/accounting` (`6.1 Đối soát đơn / Kế toán tác nghiệp`) so it follows the same Pushsale operation page structure as Sale and Warehouse workspaces.
- Fix the header/filter area that was using a modern `PageHeader + ReportFilterBar` stack and did not match the legacy operation pages.
- Rebuild the accounting table columns to mirror the old Pushsale accounting/warehouse order table and reuse the same product/money stack styling used by the other operation pages.

## Key changes

- Added `resources/js/components/operations/AccountingOperationFilters.jsx`.
- Rebuilt `resources/js/pages/Admin/Accounting/Operations.jsx` to use the accounting operation shell, not generic report layout.
- Rebuilt `resources/js/components/operations/AccountingReconTable.jsx` with legacy-style columns:
  - STT
  - Sale
  - Ngày data về / Mã đơn / Ngày chốt đơn
  - Kho / PTGH / Mã giao vận
  - Care đơn / Ghi chú KT
  - Trạng thái giao hàng / Ngày đăng đơn
  - ĐSNB
  - Sản phẩm - SL - Đơn giá
  - Thành tiền / CK / VAT SP / Phí VC / Tổng tiền
  - Đặt cọc
  - Tiền thu của khách
  - Giá dịch vụ VC
  - Phí VC hỗ trợ khách
  - Họ tên / Số điện thoại
  - Địa chỉ / Ghi chú giao hàng
  - Thao tác
- Added scoped CSS: `resources/css/pushsale-v77-accounting-operations.css`.
- Loaded the CSS through `resources/js/lib/uiShellStyles.js` after V76 so it wins cascade for accounting page only.

## Business wiring

The page still uses the existing real backend flow:

- Controller: `app/Http/Controllers/Admin/Accounting/OperationsController.php`
- Service: `app/Services/Operations/AccountingOperationService.php`
- Data: `OrderOperationPresenter::collection`, `::totals`, and `::accountingStatusTabs`
- Repository: `OrderRepositoryInterface::allFiltered`

No demo/static data was introduced.

## Notes

This change is deliberately scoped under `.ps-acc-page` to avoid changing menu, sale workspace, warehouse workspace, combo, teams, or users screens.
