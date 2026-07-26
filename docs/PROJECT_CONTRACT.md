# ERM Pushsale Project Contract

## 1. Kiến trúc giao diện

Tất cả trang admin phải đi qua `AppLayout`. Trang nghiệp vụ dùng một trong hai shell:

- `resources/js/components/layout/PushsalePageShell.jsx`: trang có title + filter chính + filter nâng cao + body.
- `resources/js/components/pushsale/PushsalePageHeader.jsx`: trang legacy cần header giống Pushsale HTML mẫu.

Không viết mỗi trang một bộ header/filter riêng nữa. Nếu thiếu use case, mở rộng component shell, không copy layout.

## 2. CSS contract

Thứ tự CSS runtime nằm ở `resources/js/lib/pushsaleStyleRegistry.js`.

- Vendor legacy: `/public/vendor/adminlte2`, `/public/vendor/font-awesome`.
- CSS base/page scoped: `resources/css/pushsale-*.css`.
- CSS cuối cùng: `resources/css/pushsale-adminlte-canonical-contract.css`.

Quy tắc: selector global sidebar/header chỉ được đặt ở file canonical cuối cùng. Các trang riêng chỉ được scope bằng class trang.

## 3. Luồng Landing Connection mới

Menu 2.4.1 chỉ tạo nguồn landing:

- loại kết nối,
- cấu hình chia số,
- tên nguồn,
- URL landing,
- kênh quảng cáo,
- upsale URL,
- sale ưu tiên,
- nhập thủ công.

Không chọn sản phẩm ở bước tạo. Bước duyệt ở `/admin/marketing/landing-approvals` mới gắn sản phẩm/gói và ngân sách rồi sync sang bảng legacy `marketing_sources` để báo cáo/lead flow cũ vẫn đọc được.

## 4. Backend rules

- Controller chỉ validate request và gọi service.
- Service chịu trách nhiệm transaction/business flow.
- Model không chứa logic validate business phức tạp.
- Runtime repair chỉ vá schema không phá dữ liệu.
- Không để route view trả 500; lỗi business phải quay lại form với validation/flash.

## 5. Test tối thiểu sau mỗi lần sửa

```bash
php artisan erm:test-all --route-smoke --smoke-limit=30 --json
php artisan erm:test-all --landing-flow --flow --json
```

Route smoke chỉ fail khi có lỗi nặng như 500/exception/PHP error. 403/404 được tính warning để log không nhiễu.

## Deploy permission contract

- `public/build` is generated output. It must stay writable by the deploy user before `pnpm build`.
- Use `deploy/fix-build-permissions.sh` before Vite build in every deploy path.
- Do not run a manual root-owned build without restoring owner to `deploy:www-data`.

## 6. Manual data distribution contract

Menu 1.5 `/admin/leads` is a real manual allocation workflow, not a demo table:

- UI posts explicit payload to `/admin/leads/distribute`.
- Backend service `DataDistributionService` chooses pending `lead_ingestions` by product/filter.
- `ManualLeadAllocationService` creates sale orders and attaches phone locks to avoid two sales calling the same customer.
- Product permission is enforced before allocation.
- Any new filter must be added to both `normalizeFilters()` and the frontend payload; do not create visual-only filters.

## 7. Sidebar/menu hover contract

Second-level menu leaves and second-level parents must use one visual contract:

- active: blue background, white text;
- hover: blue background, white text;
- no top/bottom blue border artifacts.

Because AdminLTE legacy CSS can override hover selectors, `AppSidebar.jsx` also applies a React hover class/inline fallback. Do not remove this until AdminLTE CSS is fully retired.

## v126 landing approval UI contract

- Menu 2.4.1 only creates/edits landing source information. Product/package and budget are not part of this form.
- `request_approval` is a UI/business flag for the source table `Duyệt` checkbox. It does not replace final approval.
- Menu 2.4.3 is the only place that approves landing source, attaches product/package, and syncs legacy `marketing_sources`.
- Money inputs in approval UI must display VNĐ format and submit sanitized integer values to Laravel.
- Pushsale table URL fields should be borderless by default; focus/double-click may show border/shadow for copy.


## v127 interaction contract

- Manual data distribution must always show visible feedback: pending toast, success toast, or validation/error toast.
- Landing approval must validate selected product IDs before submit and must use a stable explicit payload to backend.
- Taxonomy dialogs are full-window Pushsale-style popups with visible close action.
- Sidebar second-level leaf hover is controlled in both React state and canonical CSS; no page CSS may override `.pushsale-main-sidebar .ul2 > li.li2:hover` back to white.


## v128 Landing webhook mapping contract

- Trang tạo kết nối landing không bắt buộc sản phẩm/gói. Duyệt có thể gắn sản phẩm/gói + ngân sách, nhưng webhook vẫn phải nhận và lưu payload dù chưa map được.
- Webhook match theo thứ tự: `ps_flow/saleops_session/session_id/saleops_client_ref` → fallback SĐT trong `LEAD_PHONE_MERGE_WINDOW_MINUTES`.
- Fallback SĐT không được nối nhầm đơn cũ: chỉ auto append khi order còn cửa sổ hold; nếu không đủ điều kiện thì ghi packet review.
- Mọi payload landing phải lưu `_landing_webhook_mapping` trong `lead_ingestions.payload` để thấy đủ field đã nhận, item đã map, và field sản phẩm chưa map.
- Không trả 500/422 chỉ vì chưa map sản phẩm. Trường hợp này trả `202 Accepted`, `mapping_review=true`, lưu `needs_review`.

## v129 Sidebar runtime guard

- `AppSidebar.jsx` không được dùng biến của scope menu cấp 2 trong `ThirdLevelFlyout`.
- Khi sửa menu, phải kiểm tra runtime bằng thao tác mở hamburger + hover/click menu cấp 2/cấp 3 trên ít nhất một trang như `/admin/products/import`.

## v130 Landing source flags

- Trang 2.4.1 không được validate/gắn sản phẩm ở form tạo/sửa nguồn dữ liệu.
- `manual_import` luôn bật cho nguồn landing.
- `metadata.request_approval` luôn bật; muốn chạy live phải duyệt ở menu duyệt kết nối.
- Checkbox `Nhập TC` và `Duyệt` trong bảng chỉ là thao tác bật nhanh/hiển thị trạng thái contract, không thay thế bước duyệt chính.

## v131 UI Contract Addendum
- Page header alignment is centralized in `pushsale-adminlte-canonical-contract.css`.
- Marketing dashboard and landing approval headers must use the canonical title-left/filter-right rhythm; no page should add another broad header override file.
- Sidebar second-level menu hover must be handled in `AppSidebar.jsx` plus canonical CSS only. Do not add `pushsale-sidebar-*` files for future hover fixes.

## v133 UI contract update

- The final visual contract for native selects, React `PushsaleSelect`, date range filters, rebuilt page headers, and sidebar second-level hover lives in `resources/css/pushsale-adminlte-canonical-contract.css`.
- Date range filters must remain clickable native inputs. Do not hide them with `opacity: 0` overlays in later page CSS.
- Native selects must keep the canonical right-side caret. Do not remove appearance/caret per page.
- Second-level sidebar hover must use the same blue background as active menu items, including menu items that do not have a third-level submenu.
- Rebuilt AdminLTE pages should use a left-aligned title with a small gutter, then primary filters/actions to the right.

## v134 operation/category and modal contract

- Menu `1.8.1` is not demo-only. The left table writes `operation_categories`, the bottom table writes `operation_workflows`, and the right table writes `operation_result_settings`.
- A result with `closes_order = true` calls the same real close-order service used by sale operation, so it must be treated as business configuration.
- Combo modal tables must stay inside the dialog frame; use canonical `.ps-combo-dialog` rules rather than per-dialog inline widths.
- PageFrame title alignment must start from the 14px page gutter; do not center title text inside menu group 1/2 pages unless the original Pushsale HTML does so explicitly.

## v135 taxonomy modal contract

Product taxonomy popups (`Danh sách phân loại`, `Danh sách thuộc tính sản phẩm`, `Danh sách giá trị thuộc tính`) are full-viewport Pushsale-style dialogs. They must not rely on raw Bootstrap columns for body layout. The canonical layout is:

- full viewport dialog surface,
- compact header with close button,
- search/filter row at the top,
- two-pane body: list table left, edit/update form right.

Change only these files for future taxonomy modal work:

- `resources/js/pages/Admin/Products/Index.jsx`
- `resources/css/pushsale-adminlte-canonical-contract.css`

Do not create another product taxonomy CSS override file.

## v136 Contract bổ sung

### Sidebar hover
- Source of truth: `AppSidebar.jsx` + `pushsale-adminlte-canonical-contract.css`.
- Không tạo file CSS mới để sửa hover menu. Menu cấp 2 hover phải dùng `data-ps-second-hover` hoặc `:hover` canonical rule.

### Dialog khách hàng / Pancake
- Dialog React mới dùng `PushsaleDialog`; không nhúng lại nút close cũ trong body.
- Tab nội bộ và Pancake dùng chung `PushsaleCustomerMessagesDialog` để tránh lệch UI.
- Kiểm tra cấu hình Pancake bằng `php artisan pancake:doctor --json` trước khi test chat thật.

### Product breakdown cells
- Cột `Sản phẩm - Số lượng - Đơn giá` dùng `OrderProductsBreakdown`.
- Không viết lại HTML product breakdown theo từng trang; chỉ sửa CSS shared/canonical.

### Toast realtime / phân bổ data
- Notification realtime được coalesce theo id `pushsale-realtime-notification`.
- Phân bổ data dùng toast id `manual-data-distribution` để không spam liên tục.
